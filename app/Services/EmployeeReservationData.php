<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleWaitlistEntry;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EmployeeReservationData
{
    /** @return array<string, mixed> */
    public function dashboard(Employee $employee): array
    {
        $upcoming = $this->upcomingEntries($employee);

        return [
            'stats' => [
                'confirmedReservations' => count($upcoming['reservations']),
                'waitlistEntries' => count($upcoming['waitlists']),
            ],
            'upcomingReservations' => $upcoming['reservations'],
            'waitlists' => $upcoming['waitlists'],
            'operating_timezone' => $this->operatingTimezone(),
        ];
    }

    /**
     * @param  array{date?: string|null, route_id?: int|null, direction?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function scheduleBrowser(Employee $employee, array $filters): array
    {
        $today = CarbonImmutable::now($this->operatingTimezone())->startOfDay();
        $lastBookingDate = $today->addDays($this->bookingHorizonDays());
        $selectedDate = CarbonImmutable::parse(
            $filters['date'] ?? $today->toDateString(),
            $this->operatingTimezone()
        )->startOfDay();
        $operatingDay = mb_strtolower($selectedDate->format('l'));

        $schedules = ShuttleSchedule::query()
            ->with([
                'route:id,name,origin,destination,status',
                'vehicle:id,plate_number,vehicle_type,capacity,status',
                'driver:id,name,status',
            ])
            ->where('status', 'ACTIVE')
            ->whereIn(
                'route_id',
                ShuttleRoute::query()->select('id')->where('status', 'ACTIVE')
            )
            ->whereIn(
                'vehicle_id',
                Vehicle::query()->select('id')->where('status', 'ACTIVE')
            )
            ->whereIn(
                'driver_id',
                Driver::query()->select('id')->where('status', 'ACTIVE')
            )
            ->whereDate('effective_from', '<=', $selectedDate)
            ->where(function ($query) use ($selectedDate): void {
                $query
                    ->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $selectedDate);
            })
            ->whereJsonContains('operating_days', $operatingDay)
            ->when(
                isset($filters['route_id']),
                fn ($query) => $query->where('route_id', $filters['route_id'])
            )
            ->when(
                isset($filters['direction']),
                fn ($query) => $query->where('direction', $filters['direction'])
            )
            ->when(
                $selectedDate->equalTo($today),
                fn ($query) => $query->whereTime(
                    'departure_time',
                    '>',
                    CarbonImmutable::now($this->operatingTimezone())->format('H:i:s')
                )
            )
            ->orderBy('departure_time')
            ->orderBy('id')
            ->get();

        $scheduleIds = $schedules->modelKeys();
        $openScheduleIds = $schedules
            ->filter(
                fn (ShuttleSchedule $schedule): bool => $this
                    ->departureAt($schedule, $selectedDate)
                    ->isFuture()
            )
            ->modelKeys();
        $reservations = $scheduleIds === []
            ? collect()
            : ShuttleReservation::query()
                ->whereIn('shuttle_schedule_id', $scheduleIds)
                ->whereDate('travel_date', $selectedDate)
                ->orderBy('seat_number')
                ->get();
        $waitlistEntries = $openScheduleIds === []
            ? collect()
            : ShuttleWaitlistEntry::query()
                ->with('employee:employee_id,priority_status')
                ->whereIn('shuttle_schedule_id', $openScheduleIds)
                ->whereDate('travel_date', $selectedDate)
                ->orderBy('queued_at')
                ->orderBy('id')
                ->get();
        $reservationsBySchedule = $reservations->groupBy('shuttle_schedule_id');
        $waitlistsBySchedule = $waitlistEntries->groupBy('shuttle_schedule_id');
        $waitlistMetadata = $this->waitlistMetadata($waitlistEntries);

        return [
            'selectedDate' => $selectedDate->toDateString(),
            'dates' => collect(range(0, $this->bookingHorizonDays()))
                ->map(function (int $offset) use ($today): array {
                    $date = $today->addDays($offset);

                    return [
                        'date' => $date->toDateString(),
                        'label' => $date->format('M j'),
                        'dayLabel' => $offset === 0 ? 'Today' : $date->format('D'),
                    ];
                })
                ->all(),
            'schedules' => $schedules
                ->map(function (ShuttleSchedule $schedule) use (
                    $employee,
                    $selectedDate,
                    $reservationsBySchedule,
                    $waitlistsBySchedule,
                    $waitlistMetadata
                ): array {
                    /** @var Collection<int, ShuttleReservation> $scheduleReservations */
                    $scheduleReservations = $reservationsBySchedule->get($schedule->id, collect());
                    /** @var Collection<int, ShuttleWaitlistEntry> $scheduleWaitlist */
                    $scheduleWaitlist = $waitlistsBySchedule->get($schedule->id, collect());
                    $capacity = $this->effectiveCapacity($schedule);
                    $prioritySeatCount = $this->prioritySeatCount($capacity);
                    $firstEligibleSeat = $employee->isPriority() ? 1 : $prioritySeatCount + 1;
                    $eligibleCapacity = max(0, $capacity - $firstEligibleSeat + 1);
                    $occupiedSeats = $scheduleReservations
                        ->pluck('seat_number')
                        ->map(fn (mixed $seat): int => (int) $seat)
                        ->sort()
                        ->values();
                    $occupiedEligibleSeats = $occupiedSeats
                        ->filter(fn (int $seat): bool => $seat >= $firstEligibleSeat && $seat <= $capacity)
                        ->count();
                    $employeeReservation = $scheduleReservations
                        ->firstWhere('employee_id', $employee->getKey());
                    $employeeWaitlist = $scheduleWaitlist
                        ->firstWhere('employee_id', $employee->getKey());
                    $departureAt = $this->departureAt($schedule, $selectedDate);

                    return [
                        'id' => $schedule->id,
                        'route' => [
                            'name' => $schedule->route->name,
                            'origin' => $schedule->route->origin,
                            'destination' => $schedule->route->destination,
                        ],
                        'vehicle' => [
                            'plate_number' => $schedule->vehicle->plate_number,
                            'vehicle_type' => $schedule->vehicle->vehicle_type,
                            'capacity' => (int) $schedule->vehicle->capacity,
                        ],
                        'driver' => [
                            'name' => $schedule->driver->name,
                        ],
                        'direction' => $schedule->direction,
                        'departure_time' => mb_substr((string) $schedule->departure_time, 0, 5),
                        'effective_capacity' => $capacity,
                        'priority_seat_count' => $prioritySeatCount,
                        'occupied_seats' => $occupiedSeats->all(),
                        'available_eligible_seats' => max(0, $eligibleCapacity - $occupiedEligibleSeats),
                        'eligible_capacity' => $eligibleCapacity,
                        'is_full_for_employee' => $occupiedEligibleSeats >= $eligibleCapacity,
                        'reservation' => $employeeReservation === null
                            ? null
                            : [
                                'id' => $employeeReservation->id,
                                'seat_number' => $employeeReservation->seat_number,
                                'source' => $employeeReservation->source,
                            ],
                        'waitlist' => $employeeWaitlist === null
                            ? null
                            : [
                                'id' => $employeeWaitlist->id,
                                'position' => $waitlistMetadata[$employeeWaitlist->id]['position'],
                                'tier' => $this->priorityTier($employee),
                            ],
                        'queue_size' => $scheduleWaitlist->count(),
                        'booking_open' => $departureAt->isFuture(),
                        'departure_at' => $departureAt->toIso8601String(),
                    ];
                })
                ->all(),
            'routes' => ShuttleRoute::query()
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(['id', 'name', 'origin', 'destination']),
            'directions' => ['OUTBOUND', 'RETURN'],
            'filters' => [
                'routeId' => $filters['route_id'] ?? null,
                'direction' => $filters['direction'] ?? null,
            ],
            'bookingWindow' => [
                'startsOn' => $today->toDateString(),
                'endsOn' => $lastBookingDate->toDateString(),
            ],
            'operating_timezone' => $this->operatingTimezone(),
        ];
    }

    /** @return array<string, mixed> */
    public function reservations(Employee $employee): array
    {
        $upcoming = $this->upcomingEntries($employee);

        return [
            'reservations' => $upcoming['reservations'],
            'waitlists' => $upcoming['waitlists'],
            'operating_timezone' => $this->operatingTimezone(),
        ];
    }

    /**
     * @return array{
     *     reservations: list<array<string, mixed>>,
     *     waitlists: list<array<string, mixed>>
     * }
     */
    private function upcomingEntries(Employee $employee): array
    {
        $today = CarbonImmutable::now($this->operatingTimezone())->startOfDay();
        $lastBookingDate = $today->addDays($this->bookingHorizonDays());
        $now = CarbonImmutable::now($this->operatingTimezone());
        $scheduleRelations = [
            'schedule.route:id,name,origin,destination',
            'schedule.vehicle:id,plate_number',
        ];

        $reservations = ShuttleReservation::query()
            ->with($scheduleRelations)
            ->where('employee_id', $employee->getKey())
            ->whereBetween('travel_date', [$today->toDateString(), $lastBookingDate->toDateString()])
            ->orderBy('travel_date')
            ->orderBy('id')
            ->get()
            ->filter(
                fn (ShuttleReservation $reservation): bool => $this
                    ->departureAt($reservation->schedule, $reservation->travel_date->toImmutable())
                    ->gt($now)
            )
            ->sortBy(
                fn (ShuttleReservation $reservation): int => $this
                    ->departureAt($reservation->schedule, $reservation->travel_date->toImmutable())
                    ->getTimestamp()
            )
            ->values();

        $employeeWaitlistEntries = ShuttleWaitlistEntry::query()
            ->with([
                ...$scheduleRelations,
                'employee:employee_id,priority_status',
            ])
            ->where('employee_id', $employee->getKey())
            ->whereBetween('travel_date', [$today->toDateString(), $lastBookingDate->toDateString()])
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get()
            ->filter(
                fn (ShuttleWaitlistEntry $entry): bool => $this
                    ->departureAt($entry->schedule, $entry->travel_date->toImmutable())
                    ->gt($now)
            )
            ->values();
        $occurrenceKeys = $employeeWaitlistEntries
            ->mapWithKeys(
                fn (ShuttleWaitlistEntry $entry): array => [
                    $entry->shuttle_schedule_id.'|'.$entry->travel_date->toDateString() => true,
                ]
            );
        $waitlistScheduleIds = $employeeWaitlistEntries
            ->pluck('shuttle_schedule_id')
            ->unique()
            ->values()
            ->all();
        $occurrenceWaitlistEntries = $waitlistScheduleIds === []
            ? collect()
            : ShuttleWaitlistEntry::query()
                ->with('employee:employee_id,priority_status')
                ->whereIn('shuttle_schedule_id', $waitlistScheduleIds)
                ->whereBetween('travel_date', [$today->toDateString(), $lastBookingDate->toDateString()])
                ->orderBy('queued_at')
                ->orderBy('id')
                ->get()
                ->filter(
                    fn (ShuttleWaitlistEntry $entry): bool => $occurrenceKeys->has(
                        $entry->shuttle_schedule_id.'|'.$entry->travel_date->toDateString()
                    )
                )
                ->values();
        $waitlistMetadata = $this->waitlistMetadata($occurrenceWaitlistEntries);
        $employeeWaitlistEntries = $employeeWaitlistEntries
            ->sortBy(
                fn (ShuttleWaitlistEntry $entry): int => $this
                    ->departureAt($entry->schedule, $entry->travel_date->toImmutable())
                    ->getTimestamp()
            )
            ->values();

        return [
            'reservations' => $reservations
                ->map(fn (ShuttleReservation $reservation): array => $this->reservationItem($reservation))
                ->all(),
            'waitlists' => $employeeWaitlistEntries
                ->map(function (ShuttleWaitlistEntry $entry) use ($waitlistMetadata): array {
                    return $this->waitlistItem($entry, $waitlistMetadata[$entry->id]);
                })
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function reservationItem(ShuttleReservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'travel_date' => $reservation->travel_date->toDateString(),
            'seat_number' => $reservation->seat_number,
            'source' => $reservation->source,
            'schedule' => $this->scheduleOccurrenceItem($reservation->schedule),
        ];
    }

    /**
     * @param  array{position: int, queue_size: int}  $metadata
     * @return array<string, mixed>
     */
    private function waitlistItem(
        ShuttleWaitlistEntry $entry,
        array $metadata,
    ): array {
        return [
            'id' => $entry->id,
            'travel_date' => $entry->travel_date->toDateString(),
            'position' => $metadata['position'],
            'tier' => $this->priorityTier($entry->employee),
            'queue_size' => $metadata['queue_size'],
            'schedule' => $this->scheduleOccurrenceItem($entry->schedule),
        ];
    }

    /** @return array<string, mixed> */
    private function scheduleOccurrenceItem(ShuttleSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'direction' => $schedule->direction,
            'departure_time' => mb_substr((string) $schedule->departure_time, 0, 5),
            'route' => [
                'name' => $schedule->route->name,
                'origin' => $schedule->route->origin,
                'destination' => $schedule->route->destination,
            ],
            'vehicle' => [
                'plate_number' => $schedule->vehicle->plate_number,
            ],
        ];
    }

    /**
     * @param  Collection<int, ShuttleWaitlistEntry>  $entries
     * @return array<int, array{position: int, queue_size: int}>
     */
    private function waitlistMetadata(Collection $entries): array
    {
        $metadata = [];

        $entries
            ->groupBy(
                fn (ShuttleWaitlistEntry $entry): string => $entry->shuttle_schedule_id
                    .'|'.$entry->travel_date->toDateString()
            )
            ->each(function (Collection $occurrenceEntries) use (&$metadata): void {
                $orderedEntries = $occurrenceEntries
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

                $orderedEntries->each(
                    function (ShuttleWaitlistEntry $entry, int $index) use (
                        &$metadata,
                        $orderedEntries
                    ): void {
                        $metadata[$entry->id] = [
                            'position' => $index + 1,
                            'queue_size' => $orderedEntries->count(),
                        ];
                    }
                );
            });

        return $metadata;
    }

    private function effectiveCapacity(ShuttleSchedule $schedule): int
    {
        return (int) ($schedule->capacity_override ?? $schedule->vehicle->capacity);
    }

    private function prioritySeatCount(int $capacity): int
    {
        return min(
            $capacity,
            max(0, (int) config('shuttle.priority_seat_count', 8))
        );
    }

    private function priorityTier(Employee $employee): string
    {
        return $employee->isPriority()
            ? Employee::PRIORITY_STATUS_PRIORITY
            : Employee::PRIORITY_STATUS_REGULAR;
    }

    private function departureAt(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            $date->toDateString().' '.(string) $schedule->departure_time,
            $this->operatingTimezone()
        );
    }

    private function operatingTimezone(): string
    {
        return (string) config('shuttle.operating_timezone', 'Asia/Manila');
    }

    private function bookingHorizonDays(): int
    {
        return max(0, (int) config('shuttle.booking_horizon_days', 30));
    }
}
