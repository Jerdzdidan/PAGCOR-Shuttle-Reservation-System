<?php

namespace App\Services;

use App\Models\ShuttleSchedule;
use App\Models\ShuttleServiceOccurrence;

class ShuttleSeatPolicy
{
    public function effectiveCapacity(
        ShuttleSchedule|ShuttleServiceOccurrence $service,
    ): int {
        if ($service instanceof ShuttleServiceOccurrence) {
            return max(0, (int) $service->effective_capacity);
        }

        return max(0, (int) ($service->capacity_override ?? $service->vehicle->capacity));
    }

    /** @return list<int> */
    public function effectivePrioritySeats(
        ShuttleSchedule|ShuttleServiceOccurrence $service,
    ): array {
        $capacity = $this->effectiveCapacity($service);
        $configuredSeats = $service->priority_seats;

        if ($configuredSeats === null) {
            if ($service instanceof ShuttleServiceOccurrence) {
                return [];
            }

            return $this->defaultPrioritySeats($capacity);
        }

        return $this->normalizeSeats($configuredSeats, $capacity);
    }

    /** @return list<int> */
    public function effectiveUnavailableSeats(
        ShuttleSchedule|ShuttleServiceOccurrence $service,
    ): array {
        return $this->normalizeSeats(
            $service->unavailable_seats ?? [],
            $this->effectiveCapacity($service)
        );
    }

    /** @return list<int> */
    public function availableSeats(
        ShuttleSchedule|ShuttleServiceOccurrence $service,
    ): array {
        $capacity = $this->effectiveCapacity($service);
        $allSeats = $capacity > 0 ? range(1, $capacity) : [];

        return array_values(array_diff(
            $allSeats,
            $this->effectiveUnavailableSeats($service)
        ));
    }

    /** @return list<int> */
    public function eligibleSeats(
        ShuttleSchedule|ShuttleServiceOccurrence $service,
        bool $isPriorityEmployee,
    ): array {
        $availableSeats = $this->availableSeats($service);

        if ($isPriorityEmployee) {
            return $availableSeats;
        }

        return array_values(array_diff(
            $availableSeats,
            $this->effectivePrioritySeats($service)
        ));
    }

    public function isPrioritySeat(
        ShuttleSchedule|ShuttleServiceOccurrence $service,
        int $seatNumber,
    ): bool {
        return in_array($seatNumber, $this->effectivePrioritySeats($service), true);
    }

    public function isUnavailableSeat(
        ShuttleSchedule|ShuttleServiceOccurrence $service,
        int $seatNumber,
    ): bool {
        return in_array($seatNumber, $this->effectiveUnavailableSeats($service), true);
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
