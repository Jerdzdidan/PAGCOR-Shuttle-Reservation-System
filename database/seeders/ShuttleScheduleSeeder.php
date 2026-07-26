<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class ShuttleScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['destination' => 'Manila', 'plate_number' => 'NAA-1201', 'driver' => 'Roberto Dela Cruz', 'outbound' => '17:00:00', 'return' => '06:00:00'],
            ['destination' => 'Quezon City', 'plate_number' => 'NAA-1202', 'driver' => 'Eduardo Ramos', 'outbound' => '17:00:00', 'return' => '06:00:00'],
            ['destination' => 'Makati City', 'plate_number' => 'NAB-2301', 'driver' => 'Antonio Castillo', 'outbound' => '17:15:00', 'return' => '06:15:00'],
            ['destination' => 'Pasay City', 'plate_number' => 'NAB-2302', 'driver' => 'Noel Fernandez', 'outbound' => '17:15:00', 'return' => '06:15:00'],
            ['destination' => 'Parañaque City', 'plate_number' => 'NAC-3401', 'driver' => 'Jaime Torres', 'outbound' => '17:30:00', 'return' => '06:30:00'],
            ['destination' => 'Taguig City', 'plate_number' => 'NAC-3402', 'driver' => 'Danilo Mercado', 'outbound' => '17:30:00', 'return' => '06:30:00'],
            ['destination' => 'Mandaluyong City', 'plate_number' => 'NAD-4501', 'driver' => 'Manuel Salazar', 'outbound' => '17:45:00', 'return' => '06:45:00'],
            ['destination' => 'Pasig City', 'plate_number' => 'NAD-4502', 'driver' => 'Renato Domingo', 'outbound' => '17:45:00', 'return' => '06:45:00'],
            ['destination' => 'Caloocan City', 'plate_number' => 'NAE-5601', 'driver' => 'Victor Soriano', 'outbound' => '18:00:00', 'return' => '07:00:00'],
            ['destination' => 'Marikina City', 'plate_number' => 'NAE-5602', 'driver' => 'Alfredo Valdez', 'outbound' => '18:00:00', 'return' => '07:00:00'],
            ['destination' => 'Muntinlupa City', 'plate_number' => 'NAF-6701', 'driver' => 'Benjamin Lim', 'outbound' => '18:15:00', 'return' => '07:15:00'],
            ['destination' => 'Las Piñas City', 'plate_number' => 'NAF-6702', 'driver' => 'Ricardo Pascual', 'outbound' => '18:15:00', 'return' => '07:15:00'],
        ];

        foreach ($services as $service) {
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

            $commonAttributes = [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'effective_from' => '2026-07-01',
                'effective_until' => null,
                'capacity_override' => null,
                'status' => 'ACTIVE',
            ];

            ShuttleSchedule::query()->updateOrCreate(
                [
                    'route_id' => $route->id,
                    'direction' => 'OUTBOUND',
                    'notes' => 'Seeded weekday outbound service.',
                ],
                [
                    ...$commonAttributes,
                    'departure_time' => $service['outbound'],
                ],
            );

            ShuttleSchedule::query()->updateOrCreate(
                [
                    'route_id' => $route->id,
                    'direction' => 'RETURN',
                    'notes' => 'Seeded weekday return service.',
                ],
                [
                    ...$commonAttributes,
                    'departure_time' => $service['return'],
                ],
            );
        }
    }
}
