<?php

namespace App\Services;

use App\Models\ShuttleSchedule;

class ShuttleSeatPolicy
{
    public function effectiveCapacity(ShuttleSchedule $schedule): int
    {
        return max(0, (int) ($schedule->capacity_override ?? $schedule->vehicle->capacity));
    }

    /** @return list<int> */
    public function effectivePrioritySeats(ShuttleSchedule $schedule): array
    {
        $capacity = $this->effectiveCapacity($schedule);
        $configuredSeats = $schedule->priority_seats;

        if ($configuredSeats === null) {
            return $this->defaultPrioritySeats($capacity);
        }

        return $this->normalizeSeats($configuredSeats, $capacity);
    }

    /** @return list<int> */
    public function effectiveUnavailableSeats(ShuttleSchedule $schedule): array
    {
        return $this->normalizeSeats(
            $schedule->unavailable_seats ?? [],
            $this->effectiveCapacity($schedule)
        );
    }

    /** @return list<int> */
    public function availableSeats(ShuttleSchedule $schedule): array
    {
        $capacity = $this->effectiveCapacity($schedule);
        $allSeats = $capacity > 0 ? range(1, $capacity) : [];

        return array_values(array_diff(
            $allSeats,
            $this->effectiveUnavailableSeats($schedule)
        ));
    }

    /** @return list<int> */
    public function eligibleSeats(ShuttleSchedule $schedule, bool $isPriorityEmployee): array
    {
        $availableSeats = $this->availableSeats($schedule);

        if ($isPriorityEmployee) {
            return $availableSeats;
        }

        return array_values(array_diff(
            $availableSeats,
            $this->effectivePrioritySeats($schedule)
        ));
    }

    public function isPrioritySeat(ShuttleSchedule $schedule, int $seatNumber): bool
    {
        return in_array($seatNumber, $this->effectivePrioritySeats($schedule), true);
    }

    public function isUnavailableSeat(ShuttleSchedule $schedule, int $seatNumber): bool
    {
        return in_array($seatNumber, $this->effectiveUnavailableSeats($schedule), true);
    }

    public function defaultPrioritySeatCount(): int
    {
        return max(0, (int) config('shuttle.priority_seat_count', 8));
    }

    /** @return list<int> */
    public function defaultPrioritySeats(int $capacity): array
    {
        $lastPrioritySeat = min(max(0, $capacity), $this->defaultPrioritySeatCount());

        return $lastPrioritySeat > 0 ? range(1, $lastPrioritySeat) : [];
    }

    /**
     * @param  array<mixed>  $seats
     * @return list<int>
     */
    private function normalizeSeats(array $seats, int $capacity): array
    {
        $normalizedSeats = collect($seats)
            ->map(fn (mixed $seat): int => (int) $seat)
            ->filter(fn (int $seat): bool => $seat >= 1 && $seat <= $capacity)
            ->unique()
            ->sort()
            ->values();

        return $normalizedSeats->all();
    }
}
