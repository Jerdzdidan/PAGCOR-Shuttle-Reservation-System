<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /** Smallest unit in the fleet; fills up quickly and pushes riders to the waitlist. */
    public const COMPACT_VAN_PLATE = 'NAN-5501';

    /** Under maintenance, so future-dated bookings on its schedule are rejected. */
    public const MAINTENANCE_PLATE = 'NAP-6601';

    /** Retired unit kept for history; only an inactive schedule points at it. */
    public const RETIRED_PLATE = 'NAQ-7701';

    /** Active but unassigned, so it shows up as an idle asset in the fleet reports. */
    public const SPARE_PLATE = 'NAR-8801';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->vehicles() as $vehicle) {
            Vehicle::query()->updateOrCreate(
                ['plate_number' => $vehicle['plate_number']],
                [
                    'vehicle_type' => $vehicle['vehicle_type'],
                    'capacity' => $vehicle['capacity'],
                    'status' => $vehicle['status'],
                    'notes' => $vehicle['notes'],
                ],
            );
        }
    }

    /**
     * @return list<array{
     *     plate_number: string,
     *     vehicle_type: string,
     *     capacity: int,
     *     status: string,
     *     notes: ?string
     * }>
     */
    private function vehicles(): array
    {
        $fleet = [
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
        $vehicles = [];

        foreach ($fleet as $vehicle) {
            $vehicles[] = [
                ...$vehicle,
                'status' => 'ACTIVE',
                'notes' => 'PAGCOR employee shuttle fleet.',
            ];
        }

        return [
            ...$vehicles,
            [
                'plate_number' => self::COMPACT_VAN_PLATE,
                'vehicle_type' => 'Van',
                'capacity' => 12,
                'status' => 'ACTIVE',
                'notes' => 'Compact van used on the low-demand Cavite loop.',
            ],
            [
                'plate_number' => self::MAINTENANCE_PLATE,
                'vehicle_type' => 'Minibus',
                'capacity' => 28,
                'status' => 'MAINTENANCE',
                'notes' => 'Grounded for transmission repair; expected back next month.',
            ],
            [
                'plate_number' => self::RETIRED_PLATE,
                'vehicle_type' => 'Bus',
                'capacity' => 48,
                'status' => 'INACTIVE',
                'notes' => 'Retired from service. Kept for historical reporting only.',
            ],
            [
                'plate_number' => self::SPARE_PLATE,
                'vehicle_type' => 'Van',
                'capacity' => 14,
                'status' => 'ACTIVE',
                'notes' => null,
            ],
            /* Units behind the sandbox schedules that always have a departure
             * before and after the moment the database was seeded. */
            [
                'plate_number' => 'NAS-1101',
                'vehicle_type' => 'Minibus',
                'capacity' => 25,
                'status' => 'ACTIVE',
                'notes' => 'Sandbox unit.',
            ],
            [
                'plate_number' => 'NAS-1102',
                'vehicle_type' => 'Van',
                'capacity' => 16,
                'status' => 'ACTIVE',
                'notes' => 'Sandbox unit.',
            ],
            [
                'plate_number' => 'NAT-2201',
                'vehicle_type' => 'Bus',
                'capacity' => 44,
                'status' => 'ACTIVE',
                'notes' => 'Sandbox unit.',
            ],
            [
                'plate_number' => 'NAT-2202',
                'vehicle_type' => 'Van',
                'capacity' => 18,
                'status' => 'ACTIVE',
                'notes' => 'Sandbox unit.',
            ],
        ];
    }
}
