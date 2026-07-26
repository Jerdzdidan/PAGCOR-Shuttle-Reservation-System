<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleSchedule;
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
            ->where(function (Builder $query) use ($selectedDate, $operatingDay): void {
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
                'employee:employee_id,name,email,contact_number,department,position,priority_status',
            ])
            ->whereIn('shuttle_schedule_id', $scheduleIds)
            ->whereDate('travel_date', $selectedDate)
            ->orderBy('seat_number')
            ->orderBy('id')
            ->get()
            ->groupBy('shuttle_schedule_id');
        $waitlistsBySchedule = ShuttleWaitlistEntry::query()
            ->with([
                'employee:employee_id,name,email,contact_number,department,position,priority_status',
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
                $waitlistsBySchedule
            ): array {
                /** @var Collection<int, ShuttleReservation> $reservations */
                $reservations = $reservationsBySchedule->get($schedule->id, collect());
                /** @var Collection<int, ShuttleWaitlistEntry> $waitlistEntries */
                $waitlistEntries = $waitlistsBySchedule->get($schedule->id, collect());
                $orderedWaitlist = $this->orderedWaitlist($waitlistEntries);
                $capacity = $this->seatPolicy->effectiveCapacity($schedule);
                $prioritySeats = $this->seatPolicy->effectivePrioritySeats($schedule);
                $unavailableSeats = $this->seatPolicy->effectiveUnavailableSeats($schedule);
                $availableSeats = $this->seatPolicy->availableSeats($schedule);
                $occupiedSeats = $reservations
                    ->pluck('seat_number')
                    ->map(fn (mixed $seat): int => (int) $seat)
                    ->sort()
                    ->values();
                $occupiedAvailableSeats = $occupiedSeats
                    ->filter(fn (int $seat): bool => in_array($seat, $availableSeats, true))
                    ->count();
                $departureAt = $this->departureAt($schedule, $selectedDate);
                $operationalStatus = $this->operationalStatus(
                    $schedule,
                    $selectedDate,
                    $departureAt
                );

                return [
                    'id' => $schedule->id,
                    'travel_date' => $selectedDate->toDateString(),
                    'route' => [
                        'id' => $schedule->route->id,
                        'name' => $schedule->route->name,
                        'origin' => $schedule->route->origin,
                        'destination' => $schedule->route->destination,
                        'status' => $schedule->route->status,
                    ],
                    'vehicle' => [
                        'id' => $schedule->vehicle->id,
                        'plate_number' => $schedule->vehicle->plate_number,
                        'vehicle_type' => $schedule->vehicle->vehicle_type,
                        'capacity' => (int) $schedule->vehicle->capacity,
                        'status' => $schedule->vehicle->status,
                    ],
                    'driver' => [
                        'id' => $schedule->driver->id,
                        'name' => $schedule->driver->name,
                        'employee_id' => $schedule->driver->employee_id,
                        'contact_number' => $schedule->driver->contact_number,
                        'status' => $schedule->driver->status,
                    ],
                    'direction' => $schedule->direction,
                    'departure_time' => mb_substr((string) $schedule->departure_time, 0, 5),
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
                    'queue_size' => $orderedWaitlist->count(),
                    'waitlist_enabled' => (bool) $schedule->waitlist_enabled,
                    'waitlist_capacity' => $schedule->waitlist_capacity,
                    'reservations' => $reservations
                        ->map(fn (ShuttleReservation $reservation): array => [
                            'id' => $reservation->id,
                            'seat_number' => $reservation->seat_number,
                            'source' => $reservation->source,
                            'reserved_at' => $reservation->reserved_at->toIso8601String(),
                            'employee' => $this->employeeItem($reservation->employee),
                        ])
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
    ): string {
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
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            $selectedDate->toDateString().' '.(string) $schedule->departure_time,
            (string) config('shuttle.operating_timezone', 'Asia/Manila')
        );
    }
}
