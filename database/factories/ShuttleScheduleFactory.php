<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShuttleSchedule>
 */
class ShuttleScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['route_id' => ShuttleRoute::factory(), 'vehicle_id' => Vehicle::factory(), 'driver_id' => Driver::factory(), 'direction' => 'OUTBOUND', 'departure_time' => '07:00:00', 'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 'effective_from' => now()->toDateString(), 'effective_until' => null, 'capacity_override' => null, 'status' => 'ACTIVE', 'notes' => null];
    }
}
