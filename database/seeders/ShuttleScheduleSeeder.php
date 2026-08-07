<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Builds the recurring departure roster.
 *
 * Beyond the ordinary weekday services the roster deliberately contains one of
 * every awkward configuration: capacity overrides, blocked seats, custom and
 * empty priority blocks, disabled and capped waitlists, weekend-only and
 * everyday services, an expired schedule, one that has not started yet, an
 * inactive schedule, and a schedule whose vehicle is under maintenance.
 *
 * The four "sandbox" schedules run every day and take their departure times from
 * the moment the seeder runs, so the current day always has services that have
 * already left and services that are still open for booking.
 */
class ShuttleScheduleSeeder extends Seeder
{
    /** @var list<string> */
    public const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    /** @var list<string> */
    public const WEEKEND = ['saturday', 'sunday'];

    /** @var list<string> */
    public const EVERY_DAY = [
        'monday', 'tuesday', 'wednesday', 'thursday',
        'friday', 'saturday', 'sunday',
    ];

    /** Minutes relative to the seeding time used by the sandbox schedules. */
    private const SANDBOX_OFFSETS = [
        'departed_long_ago' => -150,
        'departed_recently' => -25,
        'departing_soon' => 75,
        'departing_later' => 240,
    ];

    private const HISTORY_START_DAYS = 120;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $today = CarbonImmutable::now(
            (string) config('shuttle.operating_timezone', 'Asia/Manila')
        )->startOfDay();
        $now = CarbonImmutable::now(
            (string) config('shuttle.operating_timezone', 'Asia/Manila')
        );
        $defaultEffectiveFrom = $today->subDays(self::HISTORY_START_DAYS)->toDateString();

        foreach ($this->definitions($now) as $definition) {
            $route = ShuttleRoute::query()
                ->where('name', ShuttleRouteSeeder::routeName($definition['destination']))
                ->firstOrFail();
            $vehicle = Vehicle::query()
                ->where('plate_number', $definition['plate_number'])
                ->firstOrFail();
            $driver = Driver::query()
                ->where('name', $definition['driver'])
                ->firstOrFail();

            /* Keyed on the vehicle rather than the time so re-seeding moves the
             * sandbox departures instead of piling up new schedules. */
            ShuttleSchedule::query()->updateOrCreate(
                [
                    'route_id' => $route->id,
                    'vehicle_id' => $vehicle->id,
                    'direction' => $definition['direction'],
                ],
                [
                    'departure_time' => $definition['departure_time'],
                    'driver_id' => $driver->id,
                    'operating_days' => $definition['operating_days'],
                    'effective_from' => $definition['effective_from'] ?? $defaultEffectiveFrom,
                    'effective_until' => $definition['effective_until'] ?? null,
                    'capacity_override' => $definition['capacity_override'] ?? null,
                    'priority_seats' => $definition['priority_seats'] ?? null,
                    'unavailable_seats' => $definition['unavailable_seats'] ?? [],
                    'waitlist_enabled' => $definition['waitlist_enabled'] ?? true,
                    'waitlist_capacity' => $definition['waitlist_capacity'] ?? null,
                    'status' => $definition['status'] ?? 'ACTIVE',
                    'notes' => $definition['notes'] ?? null,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(CarbonImmutable $now): array
    {
        $today = $now->startOfDay();

        return [
            ...$this->coreDefinitions(),
            ...$this->specialDefinitions($today),
            ...$this->sandboxDefinitions($now),
        ];
    }

    /**
     * The everyday roster: one vehicle serves the early return trip to
     * headquarters and the evening trip back home for the same city.
     *
     * @return list<array<string, mixed>>
     */
    private function coreDefinitions(): array
    {
        $roster = [
            [
                'destination' => 'Manila',
                'plate_number' => 'NAA-1201',
                'driver' => 'Roberto Dela Cruz',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '05:45',
                'outbound_time' => '17:00',
                'return_overrides' => [
                    'capacity_override' => 28,
                    'unavailable_seats' => [27, 28],
                    'notes' => 'Two rear seats are blocked for the spare tyre.',
                ],
            ],
            [
                'destination' => 'Quezon City',
                'plate_number' => 'NAA-1202',
                'driver' => 'Eduardo Ramos',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '05:50',
                'outbound_time' => '17:05',
                'outbound_overrides' => [
                    'priority_seats' => [1, 2, 3, 4],
                    'notes' => 'Only the first row is held for priority employees.',
                ],
            ],
            [
                'destination' => 'Makati City',
                'plate_number' => 'NAB-2301',
                'driver' => 'Antonio Castillo',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '05:55',
                'outbound_time' => '17:10',
                'return_overrides' => [
                    'priority_seats' => [],
                    'notes' => 'No priority block; every seat is open to all employees.',
                ],
            ],
            [
                'destination' => 'Pasay City',
                'plate_number' => 'NAB-2302',
                'driver' => 'Noel Fernandez',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:00',
                'outbound_time' => '17:15',
                'outbound_overrides' => [
                    'waitlist_enabled' => false,
                    'notes' => 'Waitlist disabled; the shuttle leaves with empty seats instead.',
                ],
            ],
            [
                'destination' => 'Parañaque City',
                'plate_number' => 'NAC-3401',
                'driver' => 'Jaime Torres',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:05',
                'outbound_time' => '17:20',
                'priority_seats' => [1, 2, 3],
                'return_overrides' => [
                    'waitlist_capacity' => 5,
                    'notes' => 'Waitlist capped at five employees.',
                ],
            ],
            [
                'destination' => 'Taguig City',
                'plate_number' => 'NAC-3402',
                'driver' => 'Danilo Mercado',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:10',
                'outbound_time' => '17:25',
                'priority_seats' => [1, 2, 3],
                'outbound_overrides' => [
                    'notes' => 'Unlimited waitlist for the busiest evening run.',
                ],
            ],
            [
                'destination' => 'Mandaluyong City',
                'plate_number' => 'NAD-4501',
                'driver' => 'Manuel Salazar',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:15',
                'outbound_time' => '17:30',
                'unavailable_seats' => [13, 14, 15],
                'notes' => 'Seats 13 to 15 are out of service pending upholstery repair.',
            ],
            [
                'destination' => 'Pasig City',
                'plate_number' => 'NAD-4502',
                'driver' => 'Renato Domingo',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:20',
                'outbound_time' => '17:35',
                'outbound_overrides' => [
                    'capacity_override' => 30,
                    'notes' => 'Capacity trimmed to 30 while cargo is carried at the back.',
                ],
            ],
            [
                'destination' => 'Caloocan City',
                'plate_number' => 'NAE-5601',
                'driver' => 'Victor Soriano',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:25',
                'outbound_time' => '17:40',
                'return_overrides' => [
                    'priority_seats' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                    'notes' => 'Extended priority block for the senior citizens group.',
                ],
            ],
            [
                'destination' => 'Marikina City',
                'plate_number' => 'NAE-5602',
                'driver' => 'Alfredo Valdez',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:30',
                'outbound_time' => '17:45',
            ],
            [
                'destination' => 'Muntinlupa City',
                'plate_number' => 'NAF-6701',
                'driver' => 'Benjamin Lim',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:35',
                'outbound_time' => '17:50',
                'priority_seats' => [1, 2, 3, 4],
            ],
            [
                'destination' => 'Las Piñas City',
                'plate_number' => 'NAF-6702',
                'driver' => 'Ricardo Pascual',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:40',
                'outbound_time' => '17:55',
                'priority_seats' => [1, 2, 3, 4],
            ],
            [
                'destination' => 'Valenzuela City',
                'plate_number' => 'NAG-7801',
                'driver' => 'Andres Villareal',
                'operating_days' => ['monday', 'wednesday', 'friday'],
                'return_time' => '06:00',
                'outbound_time' => '18:00',
            ],
            [
                'destination' => 'Malabon City',
                'plate_number' => 'NAG-7802',
                'driver' => 'Cesar Manalo',
                'operating_days' => ['monday', 'wednesday', 'friday'],
                'return_time' => '06:05',
                'outbound_time' => '18:05',
            ],
            [
                'destination' => 'Navotas City',
                'plate_number' => 'NAH-8901',
                'driver' => 'Domingo Evangelista',
                'operating_days' => ['monday', 'wednesday', 'friday'],
                'return_time' => '06:10',
                'outbound_time' => '18:10',
            ],
            [
                'destination' => 'San Juan City',
                'plate_number' => 'NAH-8902',
                'driver' => 'Ernesto Macapagal',
                'operating_days' => ['monday', 'wednesday', 'friday'],
                'return_time' => '06:15',
                'outbound_time' => '18:15',
            ],
            [
                'destination' => 'Antipolo City',
                'plate_number' => 'NAJ-9101',
                'driver' => 'Felipe Tolentino',
                'operating_days' => ['tuesday', 'thursday'],
                'return_time' => '06:20',
                'outbound_time' => '18:20',
                'priority_seats' => [1, 2, 3],
            ],
            [
                'destination' => 'Bacoor City',
                'plate_number' => 'NAJ-9102',
                'driver' => 'Gregorio Abad',
                'operating_days' => ['tuesday', 'thursday'],
                'return_time' => '06:25',
                'outbound_time' => '18:25',
                'priority_seats' => [1, 2, 3],
            ],
            [
                'destination' => 'Imus City',
                'plate_number' => 'NAK-2201',
                'driver' => DriverSeeder::EXPIRED_LICENSE_DRIVER,
                'operating_days' => self::WEEKEND,
                'return_time' => '07:00',
                'outbound_time' => '16:00',
                'notes' => 'Weekend skeleton service for duty personnel.',
            ],
            [
                'destination' => 'Dasmariñas City',
                'plate_number' => 'NAK-2202',
                'driver' => DriverSeeder::EXPIRING_LICENSE_DRIVER,
                'operating_days' => self::WEEKEND,
                'return_time' => '07:10',
                'outbound_time' => '16:10',
                'notes' => 'Weekend skeleton service for duty personnel.',
            ],
            [
                'destination' => 'Biñan City',
                'plate_number' => 'NAL-3301',
                'driver' => 'Maximo Samonte',
                'operating_days' => self::EVERY_DAY,
                'return_time' => '05:30',
                'outbound_time' => '19:00',
                'notes' => 'Runs every day of the week, including holidays.',
            ],
            [
                'destination' => 'Calamba City',
                'plate_number' => VehicleSeeder::COMPACT_VAN_PLATE,
                'driver' => 'Maximo Samonte',
                'operating_days' => self::WEEKDAYS,
                'return_time' => '06:50',
                'outbound_time' => '17:30',
                'priority_seats' => [1, 2],
                'waitlist_capacity' => 6,
                'notes' => 'Twelve-seater van; fills up first and queues the rest.',
            ],
        ];
        $definitions = [];

        foreach ($roster as $service) {
            $shared = array_diff_key($service, array_flip([
                'destination', 'plate_number', 'driver', 'operating_days',
                'return_time', 'outbound_time', 'return_overrides', 'outbound_overrides',
            ]));
            $directions = [
                ['RETURN', $service['return_time'], $service['return_overrides'] ?? []],
                ['OUTBOUND', $service['outbound_time'], $service['outbound_overrides'] ?? []],
            ];

            foreach ($directions as [$direction, $departureTime, $overrides]) {
                $definitions[] = [
                    'destination' => $service['destination'],
                    'plate_number' => $service['plate_number'],
                    'driver' => $service['driver'],
                    'direction' => $direction,
                    'departure_time' => $departureTime.':00',
                    'operating_days' => $service['operating_days'],
                    ...$shared,
                    ...$overrides,
                ];
            }
        }

        return $definitions;
    }

    /**
     * Lifecycle edge cases: a schedule that has already ended, one that starts
     * next week, an inactive one, and one riding on a vehicle in the shop.
     *
     * @return list<array<string, mixed>>
     */
    private function specialDefinitions(CarbonImmutable $today): array
    {
        return [
            [
                'destination' => 'Santa Rosa City',
                'plate_number' => 'NAL-3302',
                'driver' => 'Perfecto Bandoy',
                'direction' => 'RETURN',
                'departure_time' => '06:45:00',
                'operating_days' => self::WEEKDAYS,
                'effective_until' => $today->subDays(10)->toDateString(),
                'notes' => 'Discontinued: merged into the Biñan express from this month.',
            ],
            [
                'destination' => 'San Pedro City',
                'plate_number' => 'NAM-4401',
                'driver' => 'Rogelio Sarmiento',
                'direction' => 'OUTBOUND',
                'departure_time' => '18:30:00',
                'operating_days' => self::WEEKDAYS,
                'effective_from' => $today->addDays(7)->toDateString(),
                'notes' => 'New service; bookings open once it takes effect next week.',
            ],
            [
                'destination' => 'Muntinlupa City',
                'plate_number' => VehicleSeeder::MAINTENANCE_PLATE,
                'driver' => 'Felipe Tolentino',
                'direction' => 'OUTBOUND',
                'departure_time' => '19:30:00',
                'operating_days' => self::WEEKDAYS,
                'notes' => 'Late run; the assigned unit is currently under maintenance.',
            ],
            [
                'destination' => 'Quezon City',
                'plate_number' => VehicleSeeder::RETIRED_PLATE,
                'driver' => DriverSeeder::INACTIVE_DRIVERS[0],
                'direction' => 'OUTBOUND',
                'departure_time' => '20:00:00',
                'operating_days' => self::WEEKDAYS,
                'status' => 'INACTIVE',
                'notes' => 'Retired late-night run kept for reference.',
            ],
        ];
    }

    /**
     * Schedules pinned around the seeding time so the current day always has
     * services in every state.
     *
     * @return list<array<string, mixed>>
     */
    private function sandboxDefinitions(CarbonImmutable $now): array
    {
        return [
            [
                'destination' => 'Makati City',
                'plate_number' => 'NAS-1101',
                'driver' => 'Teofilo Aguinaldo',
                'direction' => 'OUTBOUND',
                'departure_time' => $this->sandboxTime($now, 'departed_long_ago'),
                'operating_days' => self::EVERY_DAY,
                'notes' => 'Sandbox: departed well before the database was seeded.',
            ],
            [
                'destination' => 'Pasig City',
                'plate_number' => 'NAS-1102',
                'driver' => 'Ulysses Bituin',
                'direction' => 'OUTBOUND',
                'departure_time' => $this->sandboxTime($now, 'departed_recently'),
                'operating_days' => self::EVERY_DAY,
                'priority_seats' => [1, 2, 3],
                'notes' => 'Sandbox: departed shortly before the database was seeded.',
            ],
            [
                'destination' => 'Quezon City',
                'plate_number' => 'NAT-2201',
                'driver' => 'Valentin Carreon',
                'direction' => 'RETURN',
                'departure_time' => $this->sandboxTime($now, 'departing_soon'),
                'operating_days' => self::EVERY_DAY,
                'notes' => 'Sandbox: still open for booking right after seeding.',
            ],
            [
                'destination' => 'Taguig City',
                'plate_number' => 'NAT-2202',
                'driver' => 'Wilfredo Doronila',
                'direction' => 'RETURN',
                'departure_time' => $this->sandboxTime($now, 'departing_later'),
                'operating_days' => self::EVERY_DAY,
                'priority_seats' => [1, 2, 3, 4],
                'waitlist_capacity' => 4,
                'notes' => 'Sandbox: departs later today with a small waitlist.',
            ],
        ];
    }

    /**
     * Offsets are clamped inside the day so a seeding run just after midnight or
     * just before it still produces a past or future departure as intended.
     */
    private function sandboxTime(CarbonImmutable $now, string $offsetKey): string
    {
        $minuteOfDay = min(
            1439,
            max(0, $now->hour * 60 + $now->minute + self::SANDBOX_OFFSETS[$offsetKey])
        );

        return sprintf('%02d:%02d:00', intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }
}
