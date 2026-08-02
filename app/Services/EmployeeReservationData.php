<?php

namespace App\Services;

use App\Enums\ServiceOccurrenceStatus;
use App\Models\Driver;
use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\ShuttleServiceOccurrence;
use App\Models\ShuttleWaitlistEntry;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EmployeeReservationData
{
    public function __construct(private ShuttleSeatPolicy $seatPolicy) {}

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
        $now = CarbonImmutable::now($this->operatingTimezone());
        $scheduleRelations = [
            'route:id,name,origin,destination,status',
            'vehicle:id,plate_number,vehicle_type,capacity,status',
            'driver:id,name,status',
        ];
        $serviceOccurrencesBySchedule = collect();

        if ($selectedDate->equalTo($today)) {
            $serviceOccurrencesBySchedule = ShuttleServiceOccurrence::query()
                ->whereDate('travel_date', $selectedDate)
                ->whereNotNull('shuttle_schedule_id')
                ->where('status', ServiceOccurrenceStatus::Scheduled)
                ->where('scheduled_departure_at', '>', $now)
                ->when(
                    isset($filters['route_id']),
                    fn ($query) => $query->where('route_id', $filters['route_id'])
                )
                ->when(
                    isset($filters['direction']),
                    fn ($query) => $query->where('direction', $filters['direction'])
                )
                ->orderBy('scheduled_departure_at')
                ->orderBy('id')
                ->get()
                ->keyBy('shuttle_schedule_id');

            $schedules = $serviceOccurrencesBySchedule->isEmpty()
                ? collect()
                : ShuttleSchedule::query()
                    ->with($scheduleRelations)
                    ->whereIn('id', $serviceOccurrencesBySchedule->keys())
                    ->get()
                    ->sortBy(
                        fn (ShuttleSchedule $schedule): int => $serviceOccurrencesBySchedule
                            ->get($schedule->getKey())
                            ->scheduled_departure_at
                            ->getTimestamp()
                    )
                    ->values();
        } else {
            $schedules = ShuttleSchedule::query()
                ->with($scheduleRelations)
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
                ->orderBy('departure_time')
                ->orderBy('id')
                ->get();
        }

        $scheduleIds = $schedules->pluck('id')->all();
        $openScheduleIds = $schedules
            ->filter(
                fn (ShuttleSchedule $schedule): bool => $this->bookingOpen(
                    $schedule,
                    $selectedDate,
                    $serviceOccurrencesBySchedule->get($schedule->getKey()),
                )
            )
            ->pluck('id')
            ->all();
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
                    $waitlistMetadata,
                    $serviceOccurrencesBySchedule,
                ): array {
                    /** @var Collection<int, ShuttleReservation> $scheduleReservations */
                    $scheduleReservations = $reservationsBySchedule->get($schedule->id, collect());
                    /** @var Collection<int, ShuttleWaitlistEntry> $scheduleWaitlist */
                    $scheduleWaitlist = $waitlistsBySchedule->get($schedule->id, collect());
                    /** @var ShuttleServiceOccurrence|null $serviceOccurrence */
                    $serviceOccurrence = $serviceOccurrencesBySchedule->get($schedule->id);
                    $seatConfiguration = $serviceOccurrence ?? $schedule;
                    $capacity = $this->seatPolicy->effectiveCapacity($seatConfiguration);
                    $prioritySeats = $this->seatPolicy->effectivePrioritySeats($seatConfiguration);
                    $unavailableSeats = $this->seatPolicy->effectiveUnavailableSeats($seatConfiguration);
                    $eligibleSeats = $this->seatPolicy->eligibleSeats(
                        $seatConfiguration,
                        $employee->isPriority()
                    );
                    $eligibleCapacity = count($eligibleSeats);
                    $occupiedSeats = $scheduleReservations
                        ->pluck('seat_number')
                        ->map(fn (mixed $seat): int => (int) $seat)
                        ->sort()
                        ->values();
                    $occupiedEligibleSeats = $occupiedSeats
                        ->filter(fn (int $seat): bool => in_array($seat, $eligibleSeats, true))
                        ->count();
                    $employeeReservation = $scheduleReservations
                        ->firstWhere('employee_id', $employee->getKey());
                    $employeeWaitlist = $scheduleWaitlist
                        ->firstWhere('employee_id', $employee->getKey());
                    $departureAt = $this->departureAt(
                        $schedule,
                        $selectedDate,
                        $serviceOccurrence,
                    );

                    return [
                        'id' => $schedule->id,
                        'occurrence_id' => $serviceOccurrence?->getKey(),
                        'route' => [
                            'name' => $serviceOccurrence?->route_name ?? $schedule->route->name,
                            'origin' => $serviceOccurrence?->origin ?? $schedule->route->origin,
                            'destination' => $serviceOccurrence?->destination ?? $schedule->route->destination,
                        ],
                        'vehicle' => [
                            'plate_number' => $serviceOccurrence?->plate_number
                                ?? $schedule->vehicle->plate_number,
                            'vehicle_type' => $serviceOccurrence?->vehicle_type
                                ?? $schedule->vehicle->vehicle_type,
                            'capacity' => $serviceOccurrence?->effective_capacity
                                ?? (int) $schedule->vehicle->capacity,
                        ],
                        'driver' => [
                            'name' => $serviceOccurrence?->driver_name ?? $schedule->driver->name,
                        ],
                        'direction' => $serviceOccurrence?->direction ?? $schedule->direction,
                        'departure_time' => mb_substr(
                            (string) ($serviceOccurrence?->departure_time ?? $schedule->departure_time),
                            0,
                            5,
                        ),
                        'effective_capacity' => $capacity,
                        'priority_seat_count' => count($prioritySeats),
                        'priority_seats' => $prioritySeats,
                        'unavailable_seats' => $unavailableSeats,
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
                        'waitlist_enabled' => (bool) (
                            $serviceOccurrence?->waitlist_enabled ?? $schedule->waitlist_enabled
                        ),
                        'waitlist_capacity' => $serviceOccurrence !== null
                            ? $serviceOccurrence->waitlist_capacity
                            : $schedule->waitlist_capacity,
                        'booking_open' => $this->bookingOpen(
                            $schedule,
                            $selectedDate,
                            $serviceOccurrence,
                        ),
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

        $reservationCandidates = ShuttleReservation::query()
            ->with($scheduleRelations)
            ->where('employee_id', $employee->getKey())
            ->whereBetween('travel_date', [$today->toDateString(), $lastBookingDate->toDateString()])
            ->orderBy('travel_date')
            ->orderBy('id')
            ->get();

        $waitlistCandidates = ShuttleWaitlistEntry::query()
            ->with([
                ...$scheduleRelations,
                'employee:employee_id,priority_status',
            ])
            ->where('employee_id', $employee->getKey())
            ->whereBetween('travel_date', [$today->toDateString(), $lastBookingDate->toDateString()])
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get();
        $entryScheduleIds = $reservationCandidates
            ->pluck('shuttle_schedule_id')
            ->merge($waitlistCandidates->pluck('shuttle_schedule_id'))
            ->unique()
            ->values();
        $serviceOccurrencesByKey = $entryScheduleIds->isEmpty()
            ? collect()
            : ShuttleServiceOccurrence::query()
                ->whereIn('shuttle_schedule_id', $entryScheduleIds)
                ->whereBetween('travel_date', [
                    $today->toDateString(),
                    $lastBookingDate->toDateString(),
                ])
                ->get()
                ->keyBy(
                    fn (ShuttleServiceOccurrence $occurrence): string => $this
                        ->occurrenceKey(
                            (int) $occurrence->shuttle_schedule_id,
                            $occurrence->travel_date->toImmutable(),
                        )
                );

        $reservations = $reservationCandidates
            ->filter(
                fn (ShuttleReservation $reservation): bool => $this->departureAt(
                    $reservation->schedule,
                    $reservation->travel_date->toImmutable(),
                    $serviceOccurrencesByKey->get(
                        $this->occurrenceKey(
                            (int) $reservation->shuttle_schedule_id,
                            $reservation->travel_date->toImmutable(),
                        )
                    ),
                )
                    ->gt($now)
            )
            ->sortBy(
                fn (ShuttleReservation $reservation): int => $this->departureAt(
                    $reservation->schedule,
                    $reservation->travel_date->toImmutable(),
                    $serviceOccurrencesByKey->get(
                        $this->occurrenceKey(
                            (int) $reservation->shuttle_schedule_id,
                            $reservation->travel_date->toImmutable(),
                        )
                    ),
                )
                    ->getTimestamp()
            )
            ->values();

        $employeeWaitlistEntries = $waitlistCandidates
            ->filter(
                fn (ShuttleWaitlistEntry $entry): bool => $this->departureAt(
                    $entry->schedule,
                    $entry->travel_date->toImmutable(),
                    $serviceOccurrencesByKey->get(
                        $this->occurrenceKey(
                            (int) $entry->shuttle_schedule_id,
                            $entry->travel_date->toImmutable(),
                        )
                    ),
                )
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
                fn (ShuttleWaitlistEntry $entry): int => $this->departureAt(
                    $entry->schedule,
                    $entry->travel_date->toImmutable(),
                    $serviceOccurrencesByKey->get(
                        $this->occurrenceKey(
                            (int) $entry->shuttle_schedule_id,
                            $entry->travel_date->toImmutable(),
                        )
                    ),
                )
                    ->getTimestamp()
            )
            ->values();

        return [
            'reservations' => $reservations
                ->map(
                    fn (ShuttleReservation $reservation): array => $this->reservationItem(
                        $reservation,
                        $serviceOccurrencesByKey->get(
                            $this->occurrenceKey(
                                (int) $reservation->shuttle_schedule_id,
                                $reservation->travel_date->toImmutable(),
                            )
                        ),
                    )
                )
                ->all(),
            'waitlists' => $employeeWaitlistEntries
                ->map(function (ShuttleWaitlistEntry $entry) use (
                    $waitlistMetadata,
                    $serviceOccurrencesByKey,
                ): array {
                    return $this->waitlistItem(
                        $entry,
                        $waitlistMetadata[$entry->id],
                        $serviceOccurrencesByKey->get(
                            $this->occurrenceKey(
                                (int) $entry->shuttle_schedule_id,
                                $entry->travel_date->toImmutable(),
                            )
                        ),
                    );
                })
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function reservationItem(
        ShuttleReservation $reservation,
        ?ShuttleServiceOccurrence $occurrence,
    ): array {
        return [
            'id' => $reservation->id,
            'travel_date' => $reservation->travel_date->toDateString(),
            'seat_number' => $reservation->seat_number,
            'source' => $reservation->source,
            'schedule' => $this->scheduleOccurrenceItem(
                $reservation->schedule,
                $occurrence,
            ),
        ];
    }

    /**
     * @param  array{position: int, queue_size: int}  $metadata
     * @return array<string, mixed>
     */
    private function waitlistItem(
        ShuttleWaitlistEntry $entry,
        array $metadata,
        ?ShuttleServiceOccurrence $occurrence,
    ): array {
        return [
            'id' => $entry->id,
            'travel_date' => $entry->travel_date->toDateString(),
            'position' => $metadata['position'],
            'tier' => $this->priorityTier($entry->employee),
            'queue_size' => $metadata['queue_size'],
            'schedule' => $this->scheduleOccurrenceItem($entry->schedule, $occurrence),
        ];
    }

    /** @return array<string, mixed> */
    private function scheduleOccurrenceItem(
        ShuttleSchedule $schedule,
        ?ShuttleServiceOccurrence $occurrence,
    ): array {
        return [
            'id' => $schedule->id,
            'occurrence_id' => $occurrence?->getKey(),
            'direction' => $occurrence?->direction ?? $schedule->direction,
            'departure_time' => mb_substr(
                (string) ($occurrence?->departure_time ?? $schedule->departure_time),
                0,
                5,
            ),
            'route' => [
                'name' => $occurrence?->route_name ?? $schedule->route->name,
                'origin' => $occurrence?->origin ?? $schedule->route->origin,
                'destination' => $occurrence?->destination ?? $schedule->route->destination,
            ],
            'vehicle' => [
                'plate_number' => $occurrence?->plate_number
                    ?? $schedule->vehicle->plate_number,
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

    private function priorityTier(Employee $employee): string
    {
        return $employee->isPriority()
            ? Employee::PRIORITY_STATUS_PRIORITY
            : Employee::PRIORITY_STATUS_REGULAR;
    }

    private function departureAt(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        ?ShuttleServiceOccurrence $occurrence = null,
    ): CarbonImmutable {
        if ($occurrence !== null) {
            return $occurrence->scheduled_departure_at->toImmutable();
        }

        return CarbonImmutable::parse(
            $date->toDateString().' '.(string) $schedule->departure_time,
            $this->operatingTimezone()
        );
    }

    private function bookingOpen(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        ?ShuttleServiceOccurrence $occurrence,
    ): bool {
        if (
            $occurrence !== null
            && $occurrence->status !== ServiceOccurrenceStatus::Scheduled
        ) {
            return false;
        }

        return $this->departureAt($schedule, $date, $occurrence)->isFuture();
    }

    private function occurrenceKey(
        int $scheduleId,
        CarbonImmutable $date,
    ): string {
        return $scheduleId.'|'.$date->toDateString();
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
