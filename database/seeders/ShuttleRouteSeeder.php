<?php

namespace Database\Seeders;

use App\Models\ShuttleRoute;
use Illuminate\Database\Seeder;

class ShuttleRouteSeeder extends Seeder
{
    public const ORIGIN = 'PAGCOR Headquarters';

    /** Active but never scheduled, so it can be deleted or newly rostered. */
    public const UNSCHEDULED_DESTINATION = 'Cavite City';

    /** Discontinued routes retained for historical reporting. */
    public const DISCONTINUED_DESTINATIONS = ['Baliuag', 'Lipa City'];

    /**
     * Every destination served from headquarters, in roster order.
     *
     * @return list<string>
     */
    public static function destinations(): array
    {
        return [
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
    }

    public static function routeName(string $destination): string
    {
        return self::ORIGIN.' - '.$destination;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $destinations = [
            ...self::destinations(),
            self::UNSCHEDULED_DESTINATION,
            ...self::DISCONTINUED_DESTINATIONS,
        ];

        foreach ($destinations as $destination) {
            ShuttleRoute::query()->updateOrCreate(
                ['name' => self::routeName($destination)],
                [
                    'origin' => self::ORIGIN,
                    'destination' => $destination,
                    'status' => in_array($destination, self::DISCONTINUED_DESTINATIONS, true)
                        ? 'INACTIVE'
                        : 'ACTIVE',
                ],
            );
        }
    }
}
