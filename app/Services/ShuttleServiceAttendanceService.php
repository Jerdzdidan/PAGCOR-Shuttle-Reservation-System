<?php

namespace App\Services;

use App\Enums\AttendanceRecordingMethod;
use App\Enums\ServiceAttendanceStatus;
use App\Enums\ServiceOccurrenceStatus;
use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleServiceAttendance;
use App\Models\ShuttleServiceOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShuttleServiceAttendanceService
{
    public function __construct(private EmployeeQrCredentialValidator $qrValidator) {}

    public function scan(
        ShuttleServiceOccurrence $occurrence,
        string $credential,
        User $administrator,
    ): ShuttleServiceAttendance {
        $employee = $this->qrValidator->resolve($credential);

        return DB::transaction(function () use ($occurrence, $employee, $administrator): ShuttleServiceAttendance {
            $lockedOccurrence = $this->lockOpenOccurrence($occurrence);
            $reservation = $this->reservedEmployee(
                $lockedOccurrence,
                (int) $employee->getKey()
            );
            $lockedEmployee = $this->lockActiveEmployee(
                (int) $reservation->employee_id,
                'credential'
            );
            $reservation->setRelation('employee', $lockedEmployee);

            return $this->recordBoarding(
                $lockedOccurrence,
                $reservation,
                $administrator,
                AttendanceRecordingMethod::QrScan
            );
        }, 5);
    }

    public function markBoarded(
        ShuttleServiceOccurrence $occurrence,
        ShuttleReservation $reservation,
        User $administrator,
    ): ShuttleServiceAttendance {
        return DB::transaction(function () use ($occurrence, $reservation, $administrator): ShuttleServiceAttendance {
            $lockedOccurrence = $this->lockOpenOccurrence($occurrence);
            $lockedReservation = $this->reservationForOccurrence(
                $lockedOccurrence,
                (int) $reservation->getKey()
            );
            $lockedEmployee = $this->lockActiveEmployee(
                (int) $lockedReservation->employee_id,
                'reservation'
            );
            $lockedReservation->setRelation('employee', $lockedEmployee);

            return $this->recordBoarding(
                $lockedOccurrence,
                $lockedReservation,
                $administrator,
                AttendanceRecordingMethod::Manual
            );
        }, 5);
    }

    public function recordByEmployeeCode(
        ShuttleServiceOccurrence $occurrence,
        string $employeeCode,
        User $administrator,
    ): ShuttleServiceAttendance {
        $employee = Employee::query()
            ->where('employee_code', $employeeCode)
            ->first();

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_code' => 'No employee was found with this employee ID.',
            ]);
        }

        return DB::transaction(function () use ($occurrence, $employee, $administrator): ShuttleServiceAttendance {
            $lockedOccurrence = $this->lockOpenOccurrence($occurrence);
            $reservation = $this->reservedEmployee(
                $lockedOccurrence,
                (int) $employee->getKey(),
                'employee_code'
            );
            $lockedEmployee = $this->lockActiveEmployee(
                (int) $reservation->employee_id,
                'employee_code'
            );
            $reservation->setRelation('employee', $lockedEmployee);

            return $this->recordBoarding(
                $lockedOccurrence,
                $reservation,
                $administrator,
                AttendanceRecordingMethod::Manual
            );
        }, 5);
    }

    public function unmark(
        ShuttleServiceOccurrence $occurrence,
        ShuttleReservation $reservation,
    ): void {
        DB::transaction(function () use ($occurrence, $reservation): void {
            $lockedOccurrence = $this->lockOpenOccurrence($occurrence);
            $lockedReservation = $this->reservationForOccurrence(
                $lockedOccurrence,
                (int) $reservation->getKey()
            );

            ShuttleServiceAttendance::query()
                ->where('shuttle_service_occurrence_id', $lockedOccurrence->getKey())
                ->where('shuttle_reservation_id', $lockedReservation->getKey())
                ->lockForUpdate()
                ->delete();

            $this->refreshAttendanceCounts($lockedOccurrence);
        }, 5);
    }

    public function markAllBoarded(
        ShuttleServiceOccurrence $occurrence,
        User $administrator,
    ): int {
        return DB::transaction(function () use ($occurrence, $administrator): int {
            $lockedOccurrence = $this->lockOpenOccurrence($occurrence);
            $reservations = ShuttleReservation::query()
                ->with('employee:employee_id,employee_code,name,department,priority_status,status')
                ->where('shuttle_schedule_id', $lockedOccurrence->shuttle_schedule_id)
                ->whereDate('travel_date', $lockedOccurrence->travel_date)
                ->orderBy('seat_number')
                ->lockForUpdate()
                ->get();

            $lockedEmployees = Employee::query()
                ->whereIn(
                    'employee_id',
                    $reservations->pluck('employee_id')->sort()->values()
                )
                ->orderBy('employee_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('employee_id');

            foreach ($reservations as $reservation) {
                $lockedEmployee = $lockedEmployees->get($reservation->employee_id);

                if ($lockedEmployee === null || (string) $lockedEmployee->status !== 'ACTIVE') {
                    continue;
                }

                $reservation->setRelation('employee', $lockedEmployee);

                $this->recordBoarding(
                    $lockedOccurrence,
                    $reservation,
                    $administrator,
                    AttendanceRecordingMethod::Manual,
                    refreshCounts: false,
                );
            }

            $this->refreshAttendanceCounts($lockedOccurrence);

            return ShuttleServiceAttendance::query()
                ->where('shuttle_service_occurrence_id', $lockedOccurrence->getKey())
                ->where('status', ServiceAttendanceStatus::Boarded)
                ->count();
        }, 5);
    }

    private function lockOpenOccurrence(
        ShuttleServiceOccurrence $occurrence,
    ): ShuttleServiceOccurrence {
        $lockedOccurrence = ShuttleServiceOccurrence::query()
            ->lockForUpdate()
            ->findOrFail($occurrence->getKey());

        if (
            ! in_array(
                $lockedOccurrence->status,
                [
                    ServiceOccurrenceStatus::Scheduled,
                    ServiceOccurrenceStatus::AwaitingCompletion,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'service' => 'This service has been finalized and its attendance is read-only.',
            ]);
        }

        $today = CarbonImmutable::now($this->operatingTimezone())->startOfDay();

        if ($today->lt($lockedOccurrence->travel_date->toImmutable()->startOfDay())) {
            throw ValidationException::withMessages([
                'service' => 'Passenger boarding opens at the start of the travel date.',
            ]);
        }

        return $lockedOccurrence;
    }

    private function reservedEmployee(
        ShuttleServiceOccurrence $occurrence,
        int $employeeId,
        string $errorKey = 'credential',
    ): ShuttleReservation {
        $reservation = ShuttleReservation::query()
            ->with('employee:employee_id,employee_code,name,department,priority_status,status')
            ->where('employee_id', $employeeId)
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->whereDate('travel_date', $occurrence->travel_date)
            ->lockForUpdate()
            ->first();

        if ($reservation === null) {
            throw ValidationException::withMessages([
                $errorKey => 'This employee does not have a reservation for this service.',
            ]);
        }

        return $reservation;
    }

    private function lockActiveEmployee(int $employeeId, string $errorKey): Employee
    {
        $employee = Employee::query()
            ->lockForUpdate()
            ->find($employeeId);

        if ($employee === null || (string) $employee->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                $errorKey => 'This employee is inactive and cannot board a shuttle.',
            ]);
        }

        return $employee;
    }

    private function reservationForOccurrence(
        ShuttleServiceOccurrence $occurrence,
        int $reservationId,
    ): ShuttleReservation {
        $reservation = ShuttleReservation::query()
            ->with('employee:employee_id,employee_code,name,department,priority_status,status')
            ->whereKey($reservationId)
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->whereDate('travel_date', $occurrence->travel_date)
            ->lockForUpdate()
            ->first();

        if ($reservation === null) {
            throw ValidationException::withMessages([
                'reservation' => 'The selected passenger is not reserved for this service.',
            ]);
        }

        return $reservation;
    }

    private function recordBoarding(
        ShuttleServiceOccurrence $occurrence,
        ShuttleReservation $reservation,
        User $administrator,
        AttendanceRecordingMethod $recordingMethod,
        bool $refreshCounts = true,
    ): ShuttleServiceAttendance {
        $attendance = ShuttleServiceAttendance::query()
            ->where('shuttle_service_occurrence_id', $occurrence->getKey())
            ->where('employee_id_snapshot', $reservation->employee_id)
            ->lockForUpdate()
            ->first();

        if ($attendance?->status === ServiceAttendanceStatus::Boarded) {
            return $attendance;
        }

        $attendance ??= new ShuttleServiceAttendance;
        $attendance->fill([
            'shuttle_service_occurrence_id' => $occurrence->getKey(),
            'shuttle_reservation_id' => $reservation->getKey(),
            'employee_id' => $reservation->employee_id,
            'employee_id_snapshot' => $reservation->employee_id,
            'employee_code_snapshot' => $reservation->employee->employee_code,
            'employee_name' => $reservation->employee->name,
            'department' => $reservation->employee->department,
            'priority_status' => $reservation->employee->priority_status,
            'seat_number' => $reservation->seat_number,
            'status' => ServiceAttendanceStatus::Boarded,
            'recording_method' => $recordingMethod,
            'boarded_at' => now(),
            'recorded_by' => $administrator->getKey(),
            'recorded_by_id_snapshot' => $administrator->getKey(),
            'recorded_by_name' => $administrator->name,
        ]);
        $attendance->save();

        if ($refreshCounts) {
            $this->refreshAttendanceCounts($occurrence);
        }

        return $attendance;
    }

    private function refreshAttendanceCounts(
        ShuttleServiceOccurrence $occurrence,
    ): void {
        $occurrence->forceFill([
            'reservation_count' => ShuttleReservation::query()
                ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
                ->whereDate('travel_date', $occurrence->travel_date)
                ->count(),
            'boarded_count' => ShuttleServiceAttendance::query()
                ->where('shuttle_service_occurrence_id', $occurrence->getKey())
                ->where('status', ServiceAttendanceStatus::Boarded)
                ->count(),
            'no_show_count' => ShuttleServiceAttendance::query()
                ->where('shuttle_service_occurrence_id', $occurrence->getKey())
                ->where('status', ServiceAttendanceStatus::NoShow)
                ->count(),
        ])->save();
    }

    private function operatingTimezone(): string
    {
        return (string) config('shuttle.operating_timezone', 'Asia/Manila');
    }
}
