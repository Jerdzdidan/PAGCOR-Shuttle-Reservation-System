<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = [
            ['plate_number' => 'NAA-1201', 'vehicle_type' => 'Minibus', 'capacity' => 30],
            ['plate_number' => 'NAA-1202', 'vehicle_type' => 'Minibus', 'capacity' => 30],
            ['plate_number' => 'NAB-2301', 'vehicle_type' => 'Bus', 'capacity' => 50],
            ['plate_number' => 'NAB-2302', 'vehicle_type' => 'Bus', 'capacity' => 50],
            ['plate_number' => 'NAC-3401', 'vehicle_type' => 'Van', 'capacity' => 15],
            ['plate_number' => 'NAC-3402', 'vehicle_type' => 'Van', 'capacity' => 15],
            ['plate_number' => 'NAD-4501', 'vehicle_type' => 'Minibus', 'capacity' => 35],
            ['plate_number' => 'NAD-4502', 'vehicle_type' => 'Minibus', 'capacity' => 35],
            ['plate_number' => 'NAE-5601', 'vehicle_type' => 'Bus', 'capacity' => 55],
            ['plate_number' => 'NAE-5602', 'vehicle_type' => 'Bus', 'capacity' => 55],
            ['plate_number' => 'NAF-6701', 'vehicle_type' => 'Van', 'capacity' => 18],
            ['plate_number' => 'NAF-6702', 'vehicle_type' => 'Van', 'capacity' => 18],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::query()->updateOrCreate(
                ['plate_number' => $vehicle['plate_number']],
                [
                    ...$vehicle,
                    'status' => 'ACTIVE',
                    'notes' => 'PAGCOR employee shuttle fleet.',
                ],
            );
        }
    }
}
