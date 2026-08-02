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
            ['plate_number' => 'NAA-1201', 'vehicle_type' => 'Minibus', 'capacity' => 30, 'initial_odometer_km' => '48500.0'],
            ['plate_number' => 'NAA-1202', 'vehicle_type' => 'Minibus', 'capacity' => 30, 'initial_odometer_km' => '51200.0'],
            ['plate_number' => 'NAB-2301', 'vehicle_type' => 'Bus', 'capacity' => 50, 'initial_odometer_km' => '88300.0'],
            ['plate_number' => 'NAB-2302', 'vehicle_type' => 'Bus', 'capacity' => 50, 'initial_odometer_km' => '91750.0'],
            ['plate_number' => 'NAC-3401', 'vehicle_type' => 'Van', 'capacity' => 15, 'initial_odometer_km' => '34200.0'],
            ['plate_number' => 'NAC-3402', 'vehicle_type' => 'Van', 'capacity' => 15, 'initial_odometer_km' => '37950.0'],
            ['plate_number' => 'NAD-4501', 'vehicle_type' => 'Minibus', 'capacity' => 35, 'initial_odometer_km' => '62800.0'],
            ['plate_number' => 'NAD-4502', 'vehicle_type' => 'Minibus', 'capacity' => 35, 'initial_odometer_km' => '65400.0'],
            ['plate_number' => 'NAE-5601', 'vehicle_type' => 'Bus', 'capacity' => 55, 'initial_odometer_km' => '104500.0'],
            ['plate_number' => 'NAE-5602', 'vehicle_type' => 'Bus', 'capacity' => 55, 'initial_odometer_km' => '108250.0'],
            ['plate_number' => 'NAF-6701', 'vehicle_type' => 'Van', 'capacity' => 18, 'initial_odometer_km' => '41200.0'],
            ['plate_number' => 'NAF-6702', 'vehicle_type' => 'Van', 'capacity' => 18, 'initial_odometer_km' => '43900.0'],
            ['plate_number' => 'NAG-7801', 'vehicle_type' => 'Minibus', 'capacity' => 32, 'initial_odometer_km' => '57500.0'],
            ['plate_number' => 'NAG-7802', 'vehicle_type' => 'Minibus', 'capacity' => 32, 'initial_odometer_km' => '59600.0'],
            ['plate_number' => 'NAH-8901', 'vehicle_type' => 'Bus', 'capacity' => 45, 'initial_odometer_km' => '76500.0'],
            ['plate_number' => 'NAH-8902', 'vehicle_type' => 'Bus', 'capacity' => 45, 'initial_odometer_km' => '79800.0'],
            ['plate_number' => 'NAJ-9101', 'vehicle_type' => 'Van', 'capacity' => 16, 'initial_odometer_km' => '28600.0'],
            ['plate_number' => 'NAJ-9102', 'vehicle_type' => 'Van', 'capacity' => 16, 'initial_odometer_km' => '31200.0'],
            ['plate_number' => 'NAK-2201', 'vehicle_type' => 'Minibus', 'capacity' => 40, 'initial_odometer_km' => '68400.0'],
            ['plate_number' => 'NAK-2202', 'vehicle_type' => 'Minibus', 'capacity' => 40, 'initial_odometer_km' => '71900.0'],
            ['plate_number' => 'NAL-3301', 'vehicle_type' => 'Bus', 'capacity' => 52, 'initial_odometer_km' => '112600.0'],
            ['plate_number' => 'NAL-3302', 'vehicle_type' => 'Bus', 'capacity' => 52, 'initial_odometer_km' => '116900.0'],
            ['plate_number' => 'NAM-4401', 'vehicle_type' => 'Van', 'capacity' => 20, 'initial_odometer_km' => '46800.0'],
            ['plate_number' => 'NAM-4402', 'vehicle_type' => 'Van', 'capacity' => 20, 'initial_odometer_km' => '49700.0'],
        ];

        foreach ($vehicles as $vehicle) {
            $currentOdometer = Vehicle::query()
                ->where('plate_number', $vehicle['plate_number'])
                ->value('current_odometer_km');

            Vehicle::query()->updateOrCreate(
                ['plate_number' => $vehicle['plate_number']],
                [
                    'vehicle_type' => $vehicle['vehicle_type'],
                    'capacity' => $vehicle['capacity'],
                    'current_odometer_km' => $currentOdometer
                        ?? $vehicle['initial_odometer_km'],
                    'status' => 'ACTIVE',
                    'notes' => 'PAGCOR employee shuttle fleet.',
                ],
            );
        }
    }
}
