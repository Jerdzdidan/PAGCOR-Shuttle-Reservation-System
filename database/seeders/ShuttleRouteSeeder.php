<?php

namespace Database\Seeders;

use App\Models\ShuttleRoute;
use Illuminate\Database\Seeder;

class ShuttleRouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            'Manila',
            'Quezon City',
            'Makati City',
            'Pasay City',
            'Parañaque City',
            'Taguig City',
            'Mandaluyong City',
            'Pasig City',
            'Caloocan City',
            'Marikina City',
            'Muntinlupa City',
            'Valenzuela City',
            'Las Piñas City',
            'Malabon City',
            'Navotas City',
            'San Juan City',
            'Antipolo City',
            'Bacoor City',
            'Imus City',
            'Dasmariñas City',
            'Biñan City',
            'Santa Rosa City',
            'San Pedro City',
            'Calamba City',
        ];

        foreach ($cities as $city) {
            ShuttleRoute::query()->updateOrCreate(
                ['name' => "PAGCOR Headquarters - {$city}"],
                [
                    'origin' => 'PAGCOR Headquarters',
                    'destination' => $city,
                    'status' => 'ACTIVE',
                ],
            );
        }
    }
}
