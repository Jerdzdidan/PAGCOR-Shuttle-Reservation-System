<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ShuttleScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['destination' => 'Manila', 'plate_number' => 'NAA-1201', 'driver' => 'Roberto Dela Cruz'],
            ['destination' => 'Quezon City', 'plate_number' => 'NAA-1202', 'driver' => 'Eduardo Ramos'],
            ['destination' => 'Makati City', 'plate_number' => 'NAB-2301', 'driver' => 'Antonio Castillo'],
            ['destination' => 'Pasay City', 'plate_number' => 'NAB-2302', 'driver' => 'Noel Fernandez'],
            ['destination' => 'Parañaque City', 'plate_number' => 'NAC-3401', 'driver' => 'Jaime Torres'],
            ['destination' => 'Taguig City', 'plate_number' => 'NAC-3402', 'driver' => 'Danilo Mercado'],
            ['destination' => 'Mandaluyong City', 'plate_number' => 'NAD-4501', 'driver' => 'Manuel Salazar'],
            ['destination' => 'Pasig City', 'plate_number' => 'NAD-4502', 'driver' => 'Renato Domingo'],
            ['destination' => 'Caloocan City', 'plate_number' => 'NAE-5601', 'driver' => 'Victor Soriano'],
            ['destination' => 'Marikina City', 'plate_number' => 'NAE-5602', 'driver' => 'Alfredo Valdez'],
            ['destination' => 'Muntinlupa City', 'plate_number' => 'NAF-6701', 'driver' => 'Benjamin Lim'],
            ['destination' => 'Las Piñas City', 'plate_number' => 'NAF-6702', 'driver' => 'Ricardo Pascual'],
            ['destination' => 'Valenzuela City', 'plate_number' => 'NAG-7801', 'driver' => 'Andres Villareal'],
            ['destination' => 'Malabon City', 'plate_number' => 'NAG-7802', 'driver' => 'Cesar Manalo'],
            ['destination' => 'Navotas City', 'plate_number' => 'NAH-8901', 'driver' => 'Domingo Evangelista'],
            ['destination' => 'San Juan City', 'plate_number' => 'NAH-8902', 'driver' => 'Ernesto Macapagal'],
            ['destination' => 'Antipolo City', 'plate_number' => 'NAJ-9101', 'driver' => 'Felipe Tolentino'],
            ['destination' => 'Bacoor City', 'plate_number' => 'NAJ-9102', 'driver' => 'Gregorio Abad'],
            ['destination' => 'Imus City', 'plate_number' => 'NAK-2201', 'driver' => 'Hernando Yabut'],
            ['destination' => 'Dasmariñas City', 'plate_number' => 'NAK-2202', 'driver' => 'Isagani Laurel'],
            ['destination' => 'Biñan City', 'plate_number' => 'NAL-3301', 'driver' => 'Joaquin Panganiban'],
            ['destination' => 'Santa Rosa City', 'plate_number' => 'NAL-3302', 'driver' => 'Leopoldo Tuazon'],
            ['destination' => 'San Pedro City', 'plate_number' => 'NAM-4401', 'driver' => 'Maximo Samonte'],
            ['destination' => 'Calamba City', 'plate_number' => 'NAM-4402', 'driver' => 'Nestor Cabral'],
        ];
        $today = CarbonImmutable::now(
            (string) config('shuttle.operating_timezone', 'Asia/Manila')
        )->startOfDay();
        $defaultEffectiveFrom = $today
            ->subMonths(3)
            ->startOfMonth()
            ->toDateString();

        foreach ($services as $serviceIndex => $service) {
            $route = ShuttleRoute::query()
                ->where('origin', 'PAGCOR Headquarters')
                ->where('destination', $service['destination'])
                ->firstOrFail();
            $vehicle = Vehicle::query()
                ->where('plate_number', $service['plate_number'])
                ->firstOrFail();
            $driver = Driver::query()
                ->where('name', $service['driver'])
                ->firstOrFail();

            $departures = [
                [
                    'direction' => 'OUTBOUND',
                    'departure_time' => $this->formatTime(
                        17 * 60 + ($serviceIndex % 12) * 5
                    ),
                    'notes' => 'Seeded weekday outbound service.',
                ],
                [
                    'direction' => 'RETURN',
                    'departure_time' => $this->formatTime(
                        5 * 60 + 45 + ($serviceIndex % 10) * 5
                    ),
                    'notes' => 'Seeded weekday return service.',
                ],
            ];

            foreach ($departures as $directionIndex => $departure) {
                $identity = [
                    'route_id' => $route->id,
                    'direction' => $departure['direction'],
                    'notes' => $departure['notes'],
                ];
                $existingSchedule = ShuttleSchedule::query()
                    ->where($identity)
                    ->first();
                $scheduleSequence = $serviceIndex * 2 + $directionIndex + 1;
                $futureReservedSeats = $existingSchedule?->reservations()
                    ->whereDate('travel_date', '>=', $today)
                    ->pluck('seat_number')
                    ->map(fn (mixed $seat): int => (int) $seat)
                    ->all() ?? [];
                $capacityOverride = $this->capacityOverride(
                    $vehicle->capacity,
                    $scheduleSequence
                );

                if (
                    $capacityOverride !== null
                    && $futureReservedSeats !== []
                    && max($futureReservedSeats) > $capacityOverride
                ) {
                    $capacityOverride = null;
                }

                $effectiveCapacity = $capacityOverride ?? $vehicle->capacity;
                $unavailableSeats = $this->unavailableSeats(
                    $effectiveCapacity,
                    $scheduleSequence,
                    $futureReservedSeats
                );
                $openWaitlistCount = $existingSchedule?->waitlistEntries()
                    ->whereDate('travel_date', '>=', $today)
                    ->count() ?? 0;
                $waitlistEnabled = $openWaitlistCount > 0
                    || $scheduleSequence % 11 !== 0;
                $waitlistCapacity = $waitlistEnabled
                    ? $this->waitlistCapacity($scheduleSequence)
                    : null;

                if (
                    $waitlistCapacity !== null
                    && $waitlistCapacity < $openWaitlistCount
                ) {
                    $waitlistCapacity = $openWaitlistCount;
                }

                ShuttleSchedule::query()->updateOrCreate(
                    $identity,
                    [
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                        'departure_time' => $departure['departure_time'],
                        'operating_days' => [
                            'monday',
                            'tuesday',
                            'wednesday',
                            'thursday',
                            'friday',
                        ],
                        'effective_from' => $defaultEffectiveFrom,
                        'effective_until' => null,
                        'capacity_override' => $capacityOverride,
                        'priority_seats' => range(1, 8),
                        'unavailable_seats' => $unavailableSeats,
                        'waitlist_enabled' => $waitlistEnabled,
                        'waitlist_capacity' => $waitlistCapacity,
                        'status' => 'ACTIVE',
                    ],
                );
            }
        }
    }

    private function capacityOverride(
        int $vehicleCapacity,
        int $scheduleSequence,
    ): ?int {
        return match (true) {
            $scheduleSequence % 10 === 0 => max(9, $vehicleCapacity - 1),
            $scheduleSequence % 6 === 0 => max(9, $vehicleCapacity - 2),
            default => null,
        };
    }

    /**
     * @param  list<int>  $reservedSeats
     * @return list<int>
     */
    private function unavailableSeats(
        int $effectiveCapacity,
        int $scheduleSequence,
        array $reservedSeats,
    ): array {
        $candidateSeats = [];

        if ($scheduleSequence % 5 === 0) {
            $candidateSeats[] = $effectiveCapacity;
        }

        if ($scheduleSequence % 7 === 0) {
            $candidateSeats[] = $effectiveCapacity - 1;
        }

        if ($scheduleSequence % 13 === 0) {
            $candidateSeats[] = $effectiveCapacity - 2;
        }

        return collect($candidateSeats)
            ->filter(fn (int $seat): bool => $seat > 8)
            ->reject(fn (int $seat): bool => in_array($seat, $reservedSeats, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function waitlistCapacity(int $scheduleSequence): ?int
    {
        return match ($scheduleSequence % 4) {
            1 => 10,
            2 => 20,
            3 => 30,
            default => null,
        };
    }

    private function formatTime(int $minutesAfterMidnight): string
    {
        return sprintf(
            '%02d:%02d:00',
            intdiv($minutesAfterMidnight, 60) % 24,
            $minutesAfterMidnight % 60
        );
    }
}
