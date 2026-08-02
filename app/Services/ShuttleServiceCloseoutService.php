<?php

namespace App\Services;

use App\Enums\AttendanceRecordingMethod;
use App\Enums\ServiceAttendanceStatus;
use App\Enums\ServiceOccurrenceStatus;
use App\Enums\ShuttleActivityEventType;
use App\Models\ShuttleActivityEvent;
use App\Models\ShuttleReservation;
use App\Models\ShuttleServiceAttendance;
use App\Models\ShuttleServiceCorrection;
use App\Models\ShuttleServiceOccurrence;
use App\Models\ShuttleWaitlistEntry;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShuttleServiceCloseoutService
{
    public function openingOdometer(ShuttleServiceOccurrence $occurrence): ?string
    {
        if ($occurrence->opening_odometer_km !== null) {
            return $occurrence->opening_odometer_km;
        }

        if ($occurrence->vehicle_id === null) {
            return null;
        }

        $previousReading = ShuttleServiceOccurrence::query()
            ->where('vehicle_id', $occurrence->vehicle_id)
            ->where('status', ServiceOccurrenceStatus::Completed)
            ->where('scheduled_departure_at', '<', $occurrence->scheduled_departure_at)
            ->whereNotNull('closing_odometer_km')
            ->latest('scheduled_departure_at')
            ->value('closing_odometer_km');

        if ($previousReading !== null) {
            return number_format((float) $previousReading, 1, '.', '');
        }

        $nextOpeningReading = ShuttleServiceOccurrence::query()
            ->where('vehicle_id', $occurrence->vehicle_id)
            ->where('status', ServiceOccurrenceStatus::Completed)
            ->where('scheduled_departure_at', '>', $occurrence->scheduled_departure_at)
            ->whereNotNull('opening_odometer_km')
            ->oldest('scheduled_departure_at')
            ->value('opening_odometer_km');

        if ($nextOpeningReading !== null) {
            return number_format((float) $nextOpeningReading, 1, '.', '');
        }

        $vehicleReading = Vehicle::query()
            ->whereKey($occurrence->vehicle_id)
            ->value('current_odometer_km');

        return $vehicleReading === null
            ? null
            : number_format((float) $vehicleReading, 1, '.', '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(
        ShuttleServiceOccurrence $occurrence,
        User $administrator,
        array $data,
    ): ShuttleServiceOccurrence {
        return DB::transaction(function () use ($occurrence, $administrator, $data): ShuttleServiceOccurrence {
            $this->lockVehicle($occurrence->vehicle_id);
            $lockedOccurrence = $this->lockReadyOccurrence($occurrence);
            $openingOdometer = (float) $data['opening_odometer_km'];
            $closingOdometer = (float) $data['closing_odometer_km'];

            $this->validateOdometers(
                $lockedOccurrence,
                $openingOdometer,
                $closingOdometer
            );
            $this->validateActualTimes($data);

            $reservations = $this->reservationsFor($lockedOccurrence);
            $this->finalizeAttendanceAsCompleted(
                $lockedOccurrence,
                $reservations,
                $administrator
            );
            $unservedWaitlistCount = $this->closeUnservedWaitlist(
                $lockedOccurrence,
                ServiceOccurrenceStatus::Completed
            );
            $boardedCount = $this->attendanceCount(
                $lockedOccurrence,
                ServiceAttendanceStatus::Boarded
            );
            $noShowCount = $this->attendanceCount(
                $lockedOccurrence,
                ServiceAttendanceStatus::NoShow
            );

            $lockedOccurrence->forceFill([
                'status' => ServiceOccurrenceStatus::Completed,
                'opening_odometer_km' => $openingOdometer,
                'closing_odometer_km' => $closingOdometer,
                'distance_km' => $closingOdometer - $openingOdometer,
                'actual_departure_at' => $data['actual_departure_at'] ?? null,
                'actual_arrival_at' => $data['actual_arrival_at'] ?? null,
                'operational_notes' => $data['operational_notes'] ?? null,
                'incident_notes' => $data['incident_notes'] ?? null,
                'not_operated_reason' => null,
                'reservation_count' => $reservations->count(),
                'boarded_count' => $boardedCount,
                'no_show_count' => $noShowCount,
                'unserved_waitlist_count' => $unservedWaitlistCount,
                'finalized_at' => now(),
                'finalized_by' => $administrator->getKey(),
                'finalized_by_id_snapshot' => $administrator->getKey(),
                'finalized_by_name' => $administrator->name,
            ])->save();

            $this->refreshVehicleCurrentReading(
                (int) $lockedOccurrence->vehicle_id,
                $openingOdometer
            );

            return $lockedOccurrence->refresh();
        }, 5);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function markNotOperated(
        ShuttleServiceOccurrence $occurrence,
        User $administrator,
        array $data,
    ): ShuttleServiceOccurrence {
        return DB::transaction(function () use ($occurrence, $administrator, $data): ShuttleServiceOccurrence {
            $lockedOccurrence = $this->lockReadyOccurrence($occurrence);
            $reservations = $this->reservationsFor($lockedOccurrence);

            foreach ($reservations as $reservation) {
                ShuttleServiceAttendance::query()->updateOrCreate(
                    [
                        'shuttle_service_occurrence_id' => $lockedOccurrence->getKey(),
                        'employee_id_snapshot' => $reservation->employee_id,
                    ],
                    $this->attendanceSnapshot(
                        $lockedOccurrence,
                        $reservation,
                        $administrator,
                        ServiceAttendanceStatus::ServiceNotOperated,
                        AttendanceRecordingMethod::Finalization
                    )
                );
            }

            $unservedWaitlistCount = $this->closeUnservedWaitlist(
                $lockedOccurrence,
                ServiceOccurrenceStatus::NotOperated
            );

            $lockedOccurrence->forceFill([
                'status' => ServiceOccurrenceStatus::NotOperated,
                'opening_odometer_km' => null,
                'closing_odometer_km' => null,
                'distance_km' => null,
                'actual_departure_at' => null,
                'actual_arrival_at' => null,
                'operational_notes' => $data['operational_notes'] ?? null,
                'incident_notes' => $data['incident_notes'] ?? null,
                'not_operated_reason' => $data['not_operated_reason'],
                'reservation_count' => $reservations->count(),
                'boarded_count' => 0,
                'no_show_count' => 0,
                'unserved_waitlist_count' => $unservedWaitlistCount,
                'finalized_at' => now(),
                'finalized_by' => $administrator->getKey(),
                'finalized_by_id_snapshot' => $administrator->getKey(),
                'finalized_by_name' => $administrator->name,
            ])->save();

            return $lockedOccurrence->refresh();
        }, 5);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function correct(
        ShuttleServiceOccurrence $occurrence,
        User $administrator,
        array $data,
    ): ShuttleServiceOccurrence {
        return DB::transaction(function () use ($occurrence, $administrator, $data): ShuttleServiceOccurrence {
            $this->lockVehicle($occurrence->vehicle_id);
            $lockedOccurrence = ShuttleServiceOccurrence::query()
                ->lockForUpdate()
                ->findOrFail($occurrence->getKey());

            if (! $lockedOccurrence->isFinalized()) {
                throw ValidationException::withMessages([
                    'service' => 'Only finalized services can be corrected.',
                ]);
            }

            $beforeValues = $this->auditSnapshot($lockedOccurrence);
            $updates = Arr::only($data, [
                'opening_odometer_km',
                'closing_odometer_km',
                'actual_departure_at',
                'actual_arrival_at',
                'operational_notes',
                'incident_notes',
                'not_operated_reason',
            ]);

            if ($lockedOccurrence->status === ServiceOccurrenceStatus::Completed) {
                if (
                    (array_key_exists('opening_odometer_km', $updates)
                        && $updates['opening_odometer_km'] === null)
                    || (array_key_exists('closing_odometer_km', $updates)
                        && $updates['closing_odometer_km'] === null)
                ) {
                    throw ValidationException::withMessages([
                        'odometer' => 'Completed services must retain both opening and closing odometer readings.',
                    ]);
                }

                $openingOdometer = (float) (
                    $updates['opening_odometer_km']
                    ?? $lockedOccurrence->opening_odometer_km
                );
                $closingOdometer = (float) (
                    $updates['closing_odometer_km']
                    ?? $lockedOccurrence->closing_odometer_km
                );

                $this->validateOdometers(
                    $lockedOccurrence,
                    $openingOdometer,
                    $closingOdometer
                );

                $updates['opening_odometer_km'] = $openingOdometer;
                $updates['closing_odometer_km'] = $closingOdometer;
                $updates['distance_km'] = $closingOdometer - $openingOdometer;
                unset($updates['not_operated_reason']);
            } else {
                unset(
                    $updates['opening_odometer_km'],
                    $updates['closing_odometer_km'],
                    $updates['actual_departure_at'],
                    $updates['actual_arrival_at']
                );

                $notOperatedReason = array_key_exists('not_operated_reason', $updates)
                    ? $updates['not_operated_reason']
                    : $lockedOccurrence->not_operated_reason;

                if (! is_string($notOperatedReason) || trim($notOperatedReason) === '') {
                    throw ValidationException::withMessages([
                        'not_operated_reason' => 'A not-operated reason is required.',
                    ]);
                }
            }

            $mergedTimes = [
                'actual_departure_at' => array_key_exists('actual_departure_at', $updates)
                    ? $updates['actual_departure_at']
                    : $lockedOccurrence->actual_departure_at,
                'actual_arrival_at' => array_key_exists('actual_arrival_at', $updates)
                    ? $updates['actual_arrival_at']
                    : $lockedOccurrence->actual_arrival_at,
            ];
            $this->validateActualTimes($mergedTimes);

            $lockedOccurrence->forceFill($updates)->save();

            if (($data['attendance'] ?? []) !== []) {
                $this->correctAttendance(
                    $lockedOccurrence,
                    $administrator,
                    $data['attendance']
                );
            }

            $this->refreshFinalAttendanceCounts($lockedOccurrence);
            $afterValues = $this->auditSnapshot($lockedOccurrence->refresh());

            ShuttleServiceCorrection::query()->create([
                'shuttle_service_occurrence_id' => $lockedOccurrence->getKey(),
                'corrected_by' => $administrator->getKey(),
                'corrected_by_id_snapshot' => $administrator->getKey(),
                'corrected_by_name' => $administrator->name,
                'action' => 'CORRECTION',
                'reason' => $data['correction_reason'] ?? $data['reason'],
                'before_values' => $beforeValues,
                'after_values' => $afterValues,
                'corrected_at' => now(),
            ]);

            if ($lockedOccurrence->status === ServiceOccurrenceStatus::Completed) {
                $this->refreshVehicleCurrentReading(
                    (int) $lockedOccurrence->vehicle_id,
                    (float) $lockedOccurrence->opening_odometer_km
                );
            }

            return $lockedOccurrence->refresh();
        }, 5);
    }

    public function reopen(
        ShuttleServiceOccurrence $occurrence,
        User $administrator,
        string $reason,
    ): ShuttleServiceOccurrence {
        return DB::transaction(function () use ($occurrence, $administrator, $reason): ShuttleServiceOccurrence {
            $this->lockVehicle($occurrence->vehicle_id);
            $lockedOccurrence = ShuttleServiceOccurrence::query()
                ->lockForUpdate()
                ->findOrFail($occurrence->getKey());

            if (! $lockedOccurrence->isFinalized()) {
                throw ValidationException::withMessages([
                    'service' => 'This service is already open for completion.',
                ]);
            }

            $previousStatus = $lockedOccurrence->status;
            $beforeValues = $this->auditSnapshot($lockedOccurrence);

            if ($previousStatus === ServiceOccurrenceStatus::NotOperated) {
                ShuttleServiceAttendance::query()
                    ->where('shuttle_service_occurrence_id', $lockedOccurrence->getKey())
                    ->where('status', ServiceAttendanceStatus::ServiceNotOperated)
                    ->delete();
            } else {
                ShuttleServiceAttendance::query()
                    ->where('shuttle_service_occurrence_id', $lockedOccurrence->getKey())
                    ->where('status', ServiceAttendanceStatus::NoShow)
                    ->delete();
            }

            $lockedOccurrence->forceFill([
                'status' => ServiceOccurrenceStatus::AwaitingCompletion,
                'distance_km' => null,
                'not_operated_reason' => null,
                'reservation_count' => $this->reservationCount($lockedOccurrence),
                'boarded_count' => $this->attendanceCount(
                    $lockedOccurrence,
                    ServiceAttendanceStatus::Boarded
                ),
                'no_show_count' => 0,
                'finalized_at' => null,
                'finalized_by' => null,
                'finalized_by_id_snapshot' => null,
                'finalized_by_name' => null,
            ])->save();

            $afterValues = $this->auditSnapshot($lockedOccurrence->refresh());

            ShuttleServiceCorrection::query()->create([
                'shuttle_service_occurrence_id' => $lockedOccurrence->getKey(),
                'corrected_by' => $administrator->getKey(),
                'corrected_by_id_snapshot' => $administrator->getKey(),
                'corrected_by_name' => $administrator->name,
                'action' => 'REOPEN',
                'reason' => $reason,
                'before_values' => $beforeValues,
                'after_values' => $afterValues,
                'corrected_at' => now(),
            ]);

            if ($previousStatus === ServiceOccurrenceStatus::Completed) {
                $this->refreshVehicleCurrentReading(
                    (int) $lockedOccurrence->vehicle_id,
                    (float) ($lockedOccurrence->opening_odometer_km ?? 0)
                );
            }

            return $lockedOccurrence->refresh();
        }, 5);
    }

    private function lockReadyOccurrence(
        ShuttleServiceOccurrence $occurrence,
    ): ShuttleServiceOccurrence {
        $lockedOccurrence = ShuttleServiceOccurrence::query()
            ->lockForUpdate()
            ->findOrFail($occurrence->getKey());

        if (
            $lockedOccurrence->status === ServiceOccurrenceStatus::Scheduled
            && $lockedOccurrence->scheduled_departure_at->isPast()
        ) {
            $lockedOccurrence->forceFill([
                'status' => ServiceOccurrenceStatus::AwaitingCompletion,
            ])->save();
        }

        if ($lockedOccurrence->status !== ServiceOccurrenceStatus::AwaitingCompletion) {
            throw ValidationException::withMessages([
                'service' => $lockedOccurrence->isFinalized()
                    ? 'This service has already been finalized.'
                    : 'This service cannot be finalized before its scheduled departure.',
            ]);
        }

        return $lockedOccurrence;
    }

    /**
     * @return Collection<int, ShuttleReservation>
     */
    private function reservationsFor(
        ShuttleServiceOccurrence $occurrence,
    ): Collection {
        return ShuttleReservation::query()
            ->with('employee:employee_id,employee_code,name,department,priority_status,status')
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->whereDate('travel_date', $occurrence->travel_date)
            ->orderBy('seat_number')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, ShuttleReservation>  $reservations
     */
    private function finalizeAttendanceAsCompleted(
        ShuttleServiceOccurrence $occurrence,
        Collection $reservations,
        User $administrator,
    ): void {
        foreach ($reservations as $reservation) {
            $attendance = ShuttleServiceAttendance::query()
                ->where('shuttle_service_occurrence_id', $occurrence->getKey())
                ->where('employee_id_snapshot', $reservation->employee_id)
                ->lockForUpdate()
                ->first();

            if ($attendance?->status === ServiceAttendanceStatus::Boarded) {
                continue;
            }

            $attendance ??= new ShuttleServiceAttendance;
            $attendance->fill($this->attendanceSnapshot(
                $occurrence,
                $reservation,
                $administrator,
                ServiceAttendanceStatus::NoShow,
                AttendanceRecordingMethod::Finalization
            ));
            $attendance->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceSnapshot(
        ShuttleServiceOccurrence $occurrence,
        ShuttleReservation $reservation,
        User $administrator,
        ServiceAttendanceStatus $status,
        AttendanceRecordingMethod $recordingMethod,
        mixed $boardedAt = null,
    ): array {
        return [
            'shuttle_service_occurrence_id' => $occurrence->getKey(),
            'shuttle_reservation_id' => $reservation->getKey(),
            'employee_id' => $reservation->employee_id,
            'employee_id_snapshot' => $reservation->employee_id,
            'employee_code_snapshot' => $reservation->employee->employee_code,
            'employee_name' => $reservation->employee->name,
            'department' => $reservation->employee->department,
            'priority_status' => $reservation->employee->priority_status,
            'seat_number' => $reservation->seat_number,
            'status' => $status,
            'recording_method' => $recordingMethod,
            'boarded_at' => $status === ServiceAttendanceStatus::Boarded
                ? ($boardedAt ?? now())
                : null,
            'recorded_by' => $administrator->getKey(),
            'recorded_by_id_snapshot' => $administrator->getKey(),
            'recorded_by_name' => $administrator->name,
        ];
    }

    private function closeUnservedWaitlist(
        ShuttleServiceOccurrence $occurrence,
        ServiceOccurrenceStatus $outcome,
    ): int {
        $entries = ShuttleWaitlistEntry::query()
            ->with('employee:employee_id,employee_code,name,priority_status')
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->whereDate('travel_date', $occurrence->travel_date)
            ->orderBy('queued_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($entries as $entry) {
            ShuttleActivityEvent::query()->create([
                'employee_id' => $entry->employee_id,
                'employee_id_snapshot' => $entry->employee_id,
                'employee_code_snapshot' => $entry->employee->employee_code,
                'shuttle_schedule_id' => $occurrence->shuttle_schedule_id,
                'shuttle_schedule_id_snapshot' => $occurrence->shuttle_schedule_id,
                'shuttle_service_occurrence_id' => $occurrence->getKey(),
                'route_id_snapshot' => $occurrence->route_id,
                'route_name' => $occurrence->route_name,
                'direction' => $occurrence->direction,
                'departure_time' => $occurrence->departure_time,
                'vehicle_id_snapshot' => $occurrence->vehicle_id,
                'plate_number' => $occurrence->plate_number,
                'driver_id_snapshot' => $occurrence->driver_id,
                'driver_name' => $occurrence->driver_name,
                'driver_employee_id' => $occurrence->driver_employee_id,
                'travel_date' => $occurrence->travel_date,
                'event_type' => ShuttleActivityEventType::WaitlistUnserved,
                'seat_number' => null,
                'occurred_at' => now(),
                'employee_name' => $entry->employee->name,
                'employee_priority_status' => $entry->employee->priority_status,
                'metadata' => [
                    'queued_at' => $entry->queued_at?->toIso8601String(),
                    'service_status' => $outcome->value,
                ],
            ]);
        }

        $count = $entries->count();

        if ($count > 0) {
            ShuttleWaitlistEntry::query()
                ->whereKey($entries->modelKeys())
                ->delete();
        }

        return ShuttleActivityEvent::query()
            ->where('shuttle_service_occurrence_id', $occurrence->getKey())
            ->where('event_type', ShuttleActivityEventType::WaitlistUnserved)
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $attendanceItems
     */
    private function correctAttendance(
        ShuttleServiceOccurrence $occurrence,
        User $administrator,
        array $attendanceItems,
    ): void {
        if ($occurrence->status !== ServiceOccurrenceStatus::Completed) {
            throw ValidationException::withMessages([
                'attendance' => 'Attendance can only be corrected for completed services.',
            ]);
        }

        foreach ($attendanceItems as $item) {
            $reservation = ShuttleReservation::query()
                ->with('employee:employee_id,employee_code,name,department,priority_status,status')
                ->whereKey((int) $item['reservation_id'])
                ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
                ->whereDate('travel_date', $occurrence->travel_date)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw ValidationException::withMessages([
                    'attendance' => 'A corrected passenger is not reserved for this service.',
                ]);
            }

            $status = ServiceAttendanceStatus::from((string) $item['status']);

            $attendance = ShuttleServiceAttendance::query()
                ->where('shuttle_service_occurrence_id', $occurrence->getKey())
                ->where('employee_id_snapshot', $reservation->employee_id)
                ->lockForUpdate()
                ->first();
            $snapshot = $this->attendanceSnapshot(
                $occurrence,
                $reservation,
                $administrator,
                $status,
                AttendanceRecordingMethod::Manual,
                $item['boarded_at'] ?? null
            );

            if ($attendance === null) {
                ShuttleServiceAttendance::query()->create($snapshot);

                continue;
            }

            $attendance->forceFill(Arr::except($snapshot, [
                'employee_id',
                'employee_id_snapshot',
                'employee_code_snapshot',
                'employee_name',
                'department',
                'priority_status',
            ]))->save();
        }
    }

    private function refreshFinalAttendanceCounts(
        ShuttleServiceOccurrence $occurrence,
    ): void {
        $occurrence->forceFill([
            'reservation_count' => $this->reservationCount($occurrence),
            'boarded_count' => $this->attendanceCount(
                $occurrence,
                ServiceAttendanceStatus::Boarded
            ),
            'no_show_count' => $occurrence->status === ServiceOccurrenceStatus::Completed
                ? $this->attendanceCount($occurrence, ServiceAttendanceStatus::NoShow)
                : 0,
        ])->save();
    }

    private function reservationCount(
        ShuttleServiceOccurrence $occurrence,
    ): int {
        return ShuttleReservation::query()
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->whereDate('travel_date', $occurrence->travel_date)
            ->count();
    }

    private function attendanceCount(
        ShuttleServiceOccurrence $occurrence,
        ServiceAttendanceStatus $status,
    ): int {
        return ShuttleServiceAttendance::query()
            ->where('shuttle_service_occurrence_id', $occurrence->getKey())
            ->where('status', $status)
            ->count();
    }

    private function validateOdometers(
        ShuttleServiceOccurrence $occurrence,
        float $openingOdometer,
        float $closingOdometer,
    ): void {
        $vehicleReading = null;

        if ($occurrence->vehicle_id !== null) {
            $vehicleReading = Vehicle::query()
                ->whereKey($occurrence->vehicle_id)
                ->value('current_odometer_km');
        }

        if ($closingOdometer < $openingOdometer) {
            throw ValidationException::withMessages([
                'closing_odometer_km' => 'The closing odometer must be at least the opening odometer.',
            ]);
        }

        if ($occurrence->vehicle_id === null) {
            return;
        }

        $previousClosing = ShuttleServiceOccurrence::query()
            ->whereKeyNot($occurrence->getKey())
            ->where('vehicle_id', $occurrence->vehicle_id)
            ->where('status', ServiceOccurrenceStatus::Completed)
            ->where('scheduled_departure_at', '<', $occurrence->scheduled_departure_at)
            ->whereNotNull('closing_odometer_km')
            ->latest('scheduled_departure_at')
            ->value('closing_odometer_km');

        if ($previousClosing !== null && $openingOdometer < (float) $previousClosing) {
            throw ValidationException::withMessages([
                'opening_odometer_km' => 'The opening odometer cannot be lower than the preceding completed service.',
            ]);
        }

        $nextOpening = ShuttleServiceOccurrence::query()
            ->whereKeyNot($occurrence->getKey())
            ->where('vehicle_id', $occurrence->vehicle_id)
            ->where('status', ServiceOccurrenceStatus::Completed)
            ->where('scheduled_departure_at', '>', $occurrence->scheduled_departure_at)
            ->whereNotNull('opening_odometer_km')
            ->oldest('scheduled_departure_at')
            ->value('opening_odometer_km');

        if ($nextOpening !== null && $closingOdometer > (float) $nextOpening) {
            throw ValidationException::withMessages([
                'closing_odometer_km' => 'The closing odometer cannot exceed the next completed service’s opening reading.',
            ]);
        }

        if (
            $previousClosing === null
            && $nextOpening === null
            && $vehicleReading !== null
            && $occurrence->status !== ServiceOccurrenceStatus::Completed
            && $openingOdometer < (float) $vehicleReading
        ) {
            throw ValidationException::withMessages([
                'opening_odometer_km' => 'The opening odometer cannot be lower than the vehicle’s latest known reading.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateActualTimes(array $data): void
    {
        $actualDepartureAt = $data['actual_departure_at'] ?? null;
        $actualArrivalAt = $data['actual_arrival_at'] ?? null;

        if (
            $actualDepartureAt !== null
            && $actualArrivalAt !== null
            && strtotime((string) $actualArrivalAt) <= strtotime((string) $actualDepartureAt)
        ) {
            throw ValidationException::withMessages([
                'actual_arrival_at' => 'The actual arrival must be after the actual departure.',
            ]);
        }
    }

    private function refreshVehicleCurrentReading(
        int $vehicleId,
        float $fallbackReading,
    ): void {
        $vehicle = Vehicle::query()
            ->lockForUpdate()
            ->find($vehicleId);

        if ($vehicle === null) {
            return;
        }

        $latestReading = ShuttleServiceOccurrence::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', ServiceOccurrenceStatus::Completed)
            ->whereNotNull('closing_odometer_km')
            ->latest('scheduled_departure_at')
            ->value('closing_odometer_km');

        $vehicle->forceFill([
            'current_odometer_km' => $latestReading ?? $fallbackReading,
        ])->save();
    }

    private function lockVehicle(?int $vehicleId): void
    {
        if ($vehicleId === null) {
            return;
        }

        Vehicle::query()
            ->whereKey($vehicleId)
            ->lockForUpdate()
            ->first();
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(
        ShuttleServiceOccurrence $occurrence,
    ): array {
        $attendances = ShuttleServiceAttendance::query()
            ->where('shuttle_service_occurrence_id', $occurrence->getKey())
            ->orderBy('seat_number')
            ->get()
            ->map(fn (ShuttleServiceAttendance $attendance): array => [
                'reservation_id' => $attendance->shuttle_reservation_id,
                'employee_id' => $attendance->employee_id_snapshot,
                'employee_code' => $attendance->employee_code_snapshot,
                'employee_name' => $attendance->employee_name,
                'department' => $attendance->department,
                'priority_status' => $attendance->priority_status,
                'seat_number' => $attendance->seat_number,
                'status' => $attendance->status->value,
                'recording_method' => $attendance->recording_method->value,
                'boarded_at' => $attendance->boarded_at?->toIso8601String(),
                'recorded_by' => $attendance->recorded_by_id_snapshot
                    ?? $attendance->recorded_by,
                'recorded_by_name' => $attendance->recorded_by_name,
            ])
            ->all();

        return [
            'status' => $occurrence->status->value,
            'opening_odometer_km' => $occurrence->opening_odometer_km,
            'closing_odometer_km' => $occurrence->closing_odometer_km,
            'distance_km' => $occurrence->distance_km,
            'actual_departure_at' => $occurrence->actual_departure_at?->toIso8601String(),
            'actual_arrival_at' => $occurrence->actual_arrival_at?->toIso8601String(),
            'operational_notes' => $occurrence->operational_notes,
            'incident_notes' => $occurrence->incident_notes,
            'not_operated_reason' => $occurrence->not_operated_reason,
            'reservation_count' => $occurrence->reservation_count,
            'boarded_count' => $occurrence->boarded_count,
            'no_show_count' => $occurrence->no_show_count,
            'unserved_waitlist_count' => $occurrence->unserved_waitlist_count,
            'finalized_at' => $occurrence->finalized_at?->toIso8601String(),
            'finalized_by' => $occurrence->finalized_by_id_snapshot
                ?? $occurrence->finalized_by,
            'finalized_by_name' => $occurrence->finalized_by_name,
            'attendance' => $attendances,
        ];
    }
}
