<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleServiceOccurrence;
use App\Models\ShuttleWaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminScheduleData
{
    public function __construct(private ShuttleSeatPolicy $seatPolicy) {}

    /** @return list<array<string, mixed>> */
    public function occurrences(CarbonImmutable $selectedDate): array
    {
        $operatingDay = mb_strtolower($selectedDate->format('l'));
        $serviceOccurrencesBySchedule = ShuttleServiceOccurrence::query()
            ->with([
                'attendances:id,shuttle_service_occurrence_id,shuttle_reservation_id,employee_id,status,recording_method,boarded_at',
            ])
            ->whereNotNull('shuttle_schedule_id')
            ->whereDate('travel_date', $selectedDate)
            ->get()
            ->keyBy('shuttle_schedule_id');
        $materializedScheduleIds = $serviceOccurrencesBySchedule->keys();
        $schedules = ShuttleSchedule::query()
            ->select([
                'id',
                'route_id',
                'vehicle_id',
                'driver_id',
                'direction',
                'departure_time',
                'operating_days',
                'effective_from',
                'effective_until',
                'capacity_override',
                'priority_seats',
                'unavailable_seats',
                'waitlist_enabled',
                'waitlist_capacity',
                'status',
            ])
            ->with([
                'route:id,name,origin,destination,status',
                'vehicle:id,plate_number,vehicle_type,capacity,status',
                'driver:id,name,employee_id,contact_number,status',
            ])
            ->where(function (Builder $query) use (
                $selectedDate,
                $operatingDay,
                $materializedScheduleIds,
            ): void {
                $query
                    ->where(function (Builder $operatingQuery) use ($selectedDate, $operatingDay): void {
                        $operatingQuery
                            ->whereDate('effective_from', '<=', $selectedDate)
                            ->where(function (Builder $effectiveQuery) use ($selectedDate): void {
                                $effectiveQuery
                                    ->whereNull('effective_until')
                                    ->orWhereDate('effective_until', '>=', $selectedDate);
                            })
                            ->whereJsonContains('operating_days', $operatingDay);
                    })
                    ->orWhereIn(
                        'id',
                        ShuttleReservation::query()
                            ->select('shuttle_schedule_id')
                            ->whereDate('travel_date', $selectedDate)
                    )
                    ->orWhereIn(
                        'id',
                        ShuttleWaitlistEntry::query()
                            ->select('shuttle_schedule_id')
                            ->whereDate('travel_date', $selectedDate)
                    );

                if ($materializedScheduleIds->isNotEmpty()) {
                    $query->orWhereIn('id', $materializedScheduleIds);
                }
            })
            ->orderBy('departure_time')
            ->orderBy('id')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $scheduleIds = $schedules->modelKeys();
        $reservationsBySchedule = ShuttleReservation::query()
            ->with([
                'employee:employee_id,employee_code,name,email,contact_number,department,position,priority_status',
            ])
            ->whereIn('shuttle_schedule_id', $scheduleIds)
            ->whereDate('travel_date', $selectedDate)
            ->orderBy('seat_number')
            ->orderBy('id')
            ->get()
            ->groupBy('shuttle_schedule_id');
        $waitlistsBySchedule = ShuttleWaitlistEntry::query()
            ->with([
                'employee:employee_id,employee_code,name,email,contact_number,department,position,priority_status',
            ])
            ->whereIn('shuttle_schedule_id', $scheduleIds)
            ->whereDate('travel_date', $selectedDate)
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get()
            ->groupBy('shuttle_schedule_id');

        return $schedules
            ->map(function (ShuttleSchedule $schedule) use (
                $selectedDate,
                $reservationsBySchedule,
                $waitlistsBySchedule,
                $serviceOccurrencesBySchedule,
            ): array {
                /** @var Collection<int, ShuttleReservation> $reservations */
                $reservations = $reservationsBySchedule->get($schedule->id, collect());
                /** @var Collection<int, ShuttleWaitlistEntry> $waitlistEntries */
                $waitlistEntries = $waitlistsBySchedule->get($schedule->id, collect());
                $serviceOccurrence = $serviceOccurrencesBySchedule->get($schedule->id);
                $attendancesByReservation = $serviceOccurrence?->attendances
                    ->keyBy('shuttle_reservation_id') ?? collect();
                $orderedWaitlist = $this->orderedWaitlist($waitlistEntries);
                $seatConfiguration = $serviceOccurrence ?? $schedule;
                $capacity = $this->seatPolicy->effectiveCapacity($seatConfiguration);
                $prioritySeats = $this->seatPolicy->effectivePrioritySeats($seatConfiguration);
                $unavailableSeats = $this->seatPolicy->effectiveUnavailableSeats($seatConfiguration);
                $availableSeats = $this->seatPolicy->availableSeats($seatConfiguration);
                $occupiedSeats = $reservations
                    ->pluck('seat_number')
                    ->map(fn (mixed $seat): int => (int) $seat)
                    ->sort()
                    ->values();
                $occupiedAvailableSeats = $occupiedSeats
                    ->filter(fn (int $seat): bool => in_array($seat, $availableSeats, true))
                    ->count();
                $departureAt = $this->departureAt(
                    $schedule,
                    $selectedDate,
                    $serviceOccurrence,
                );
                $operationalStatus = $this->operationalStatus(
                    $schedule,
                    $selectedDate,
                    $departureAt,
                    $serviceOccurrence,
                );

                return [
                    'id' => $schedule->id,
                    'occurrence_id' => $serviceOccurrence?->id,
                    'lifecycle_state' => $serviceOccurrence?->status->value,
                    'travel_date' => $selectedDate->toDateString(),
                    'route' => [
                        'id' => $serviceOccurrence?->route_id ?? $schedule->route->id,
                        'name' => $serviceOccurrence?->route_name ?? $schedule->route->name,
                        'origin' => $serviceOccurrence?->origin ?? $schedule->route->origin,
                        'destination' => $serviceOccurrence?->destination ?? $schedule->route->destination,
                        'status' => $schedule->route->status,
                    ],
                    'vehicle' => [
                        'id' => $serviceOccurrence?->vehicle_id ?? $schedule->vehicle->id,
                        'plate_number' => $serviceOccurrence?->plate_number
                            ?? $schedule->vehicle->plate_number,
                        'vehicle_type' => $serviceOccurrence !== null
                            ? $serviceOccurrence->vehicle_type
                            : $schedule->vehicle->vehicle_type,
                        'capacity' => $serviceOccurrence?->effective_capacity
                            ?? (int) $schedule->vehicle->capacity,
                        'status' => $schedule->vehicle->status,
                    ],
                    'driver' => [
                        'id' => $serviceOccurrence?->driver_id ?? $schedule->driver->id,
                        'name' => $serviceOccurrence?->driver_name ?? $schedule->driver->name,
                        'employee_id' => $serviceOccurrence !== null
                            ? $serviceOccurrence->driver_employee_id
                            : $schedule->driver->employee_id,
                        'contact_number' => $serviceOccurrence !== null
                            ? null
                            : $schedule->driver->contact_number,
                        'status' => $schedule->driver->status,
                    ],
                    'direction' => $serviceOccurrence?->direction ?? $schedule->direction,
                    'departure_time' => mb_substr(
                        (string) ($serviceOccurrence?->departure_time ?? $schedule->departure_time),
                        0,
                        5,
                    ),
                    'departure_at' => $departureAt->toIso8601String(),
                    'schedule_status' => $schedule->status,
                    'operational_status' => $operationalStatus,
                    'booking_open' => $operationalStatus === 'UPCOMING',
                    'effective_capacity' => $capacity,
                    'priority_seat_count' => count($prioritySeats),
                    'priority_seats' => $prioritySeats,
                    'unavailable_seats' => $unavailableSeats,
                    'occupied_seats' => $occupiedSeats->all(),
                    'usable_seat_count' => count($availableSeats),
                    'available_count' => max(
                        0,
                        count($availableSeats) - $occupiedAvailableSeats
                    ),
                    'reserved_count' => $reservations->count(),
                    'attendance_totals' => [
                        'boarded' => $serviceOccurrence?->boarded_count ?? 0,
                        'no_show' => $serviceOccurrence?->no_show_count ?? 0,
                        'service_not_operated' => $serviceOccurrence?->attendances
                            ->where('status.value', 'SERVICE_NOT_OPERATED')
                            ->count() ?? 0,
                        'unmarked' => max(
                            0,
                            $reservations->count() - ($serviceOccurrence?->attendances->count() ?? 0),
                        ),
                    ],
                    'queue_size' => $orderedWaitlist->count(),
                    'waitlist_enabled' => (bool) (
                        $serviceOccurrence?->waitlist_enabled ?? $schedule->waitlist_enabled
                    ),
                    'waitlist_capacity' => $serviceOccurrence !== null
                        ? $serviceOccurrence->waitlist_capacity
                        : $schedule->waitlist_capacity,
                    'reservations' => $reservations
                        ->map(function (ShuttleReservation $reservation) use ($attendancesByReservation): array {
                            $attendance = $attendancesByReservation->get($reservation->id);

                            return [
                                'id' => $reservation->id,
                                'seat_number' => $reservation->seat_number,
                                'source' => $reservation->source,
                                'reserved_at' => $reservation->reserved_at->toIso8601String(),
                                'employee' => $this->employeeItem($reservation->employee),
                                'attendance' => $attendance === null ? null : [
                                    'status' => $attendance->status->value,
                                    'recording_method' => $attendance->recording_method->value,
                                    'boarded_at' => $attendance->boarded_at?->toIso8601String(),
                                ],
                            ];
                        })
                        ->values()
                        ->all(),
                    'waitlist' => $orderedWaitlist
                        ->map(fn (ShuttleWaitlistEntry $entry, int $index): array => [
                            'id' => $entry->id,
                            'position' => $index + 1,
                            'tier' => $entry->employee->priority_status,
                            'queued_at' => $entry->queued_at->toIso8601String(),
                            'employee' => $this->employeeItem($entry->employee),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('departure_at')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ShuttleWaitlistEntry>  $entries
     * @return Collection<int, ShuttleWaitlistEntry>
     */
    private function orderedWaitlist(Collection $entries): Collection
    {
        return $entries
            ->sort(function (ShuttleWaitlistEntry $first, ShuttleWaitlistEntry $second): int {
                $priorityComparison = (int) $second->employee->isPriority()
                    <=> (int) $first->employee->isPriority();

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                $queuedAtComparison = $first->queued_at->getTimestamp()
                    <=> $second->queued_at->getTimestamp();

                if ($queuedAtComparison !== 0) {
                    return $queuedAtComparison;
                }

                return $first->id <=> $second->id;
            })
            ->values();
    }

    /** @return array<string, mixed> */
    private function employeeItem(Employee $employee): array
    {
        return $employee->only([
            'employee_id',
            'employee_code',
            'name',
            'email',
            'contact_number',
            'department',
            'position',
            'priority_status',
        ]);
    }

    private function operationalStatus(
        ShuttleSchedule $schedule,
        CarbonImmutable $selectedDate,
        CarbonImmutable $departureAt,
        ?ShuttleServiceOccurrence $serviceOccurrence,
    ): string {
        if ($serviceOccurrence !== null) {
            if ($serviceOccurrence->status->value === 'SCHEDULED') {
                return $departureAt->isPast() ? 'AWAITING_COMPLETION' : 'UPCOMING';
            }

            return match ($serviceOccurrence->status->value) {
                'AWAITING_COMPLETION' => 'AWAITING_COMPLETION',
                'COMPLETED' => 'COMPLETED',
                'NOT_OPERATED' => 'NOT_OPERATED',
                default => 'UNAVAILABLE',
            };
        }

        if (
            ! $this->operatesOn($schedule, $selectedDate)
            || $schedule->status !== 'ACTIVE'
            || $schedule->route->status !== 'ACTIVE'
            || $schedule->vehicle->status !== 'ACTIVE'
            || $schedule->driver->status !== 'ACTIVE'
        ) {
            return 'UNAVAILABLE';
        }

        return $departureAt->isPast() ? 'DEPARTED' : 'UPCOMING';
    }

    private function operatesOn(
        ShuttleSchedule $schedule,
        CarbonImmutable $selectedDate,
    ): bool {
        if ($selectedDate->lt($schedule->effective_from->toImmutable())) {
            return false;
        }

        if (
            $schedule->effective_until !== null
            && $selectedDate->gt($schedule->effective_until->toImmutable())
        ) {
            return false;
        }

        return in_array(
            mb_strtolower($selectedDate->format('l')),
            $schedule->operating_days ?? [],
            true
        );
    }

    private function departureAt(
        ShuttleSchedule $schedule,
        CarbonImmutable $selectedDate,
        ?ShuttleServiceOccurrence $serviceOccurrence = null,
    ): CarbonImmutable {
        if ($serviceOccurrence !== null) {
            return $serviceOccurrence->scheduled_departure_at->toImmutable();
        }

        return CarbonImmutable::parse(
            $selectedDate->toDateString().' '.(string) $schedule->departure_time,
            (string) config('shuttle.operating_timezone', 'Asia/Manila')
        );
    }
}
