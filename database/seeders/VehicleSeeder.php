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
            ['plate_number' => 'NAG-7801', 'vehicle_type' => 'Minibus', 'capacity' => 32],
            ['plate_number' => 'NAG-7802', 'vehicle_type' => 'Minibus', 'capacity' => 32],
            ['plate_number' => 'NAH-8901', 'vehicle_type' => 'Bus', 'capacity' => 45],
            ['plate_number' => 'NAH-8902', 'vehicle_type' => 'Bus', 'capacity' => 45],
            ['plate_number' => 'NAJ-9101', 'vehicle_type' => 'Van', 'capacity' => 16],
            ['plate_number' => 'NAJ-9102', 'vehicle_type' => 'Van', 'capacity' => 16],
            ['plate_number' => 'NAK-2201', 'vehicle_type' => 'Minibus', 'capacity' => 40],
            ['plate_number' => 'NAK-2202', 'vehicle_type' => 'Minibus', 'capacity' => 40],
            ['plate_number' => 'NAL-3301', 'vehicle_type' => 'Bus', 'capacity' => 52],
            ['plate_number' => 'NAL-3302', 'vehicle_type' => 'Bus', 'capacity' => 52],
            ['plate_number' => 'NAM-4401', 'vehicle_type' => 'Van', 'capacity' => 20],
            ['plate_number' => 'NAM-4402', 'vehicle_type' => 'Van', 'capacity' => 20],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::query()->updateOrCreate(
                ['plate_number' => $vehicle['plate_number']],
                [
                    'vehicle_type' => $vehicle['vehicle_type'],
                    'capacity' => $vehicle['capacity'],
                    'status' => 'ACTIVE',
                    'notes' => 'PAGCOR employee shuttle fleet.',
                ],
            );
        }
    }
}
