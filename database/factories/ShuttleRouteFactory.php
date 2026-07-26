<?php

namespace Database\Factories;

use App\Models\ShuttleRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShuttleRoute>
 */
class ShuttleRouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->city().' Shuttle', 'origin' => fake()->city(), 'destination' => fake()->city(), 'status' => 'ACTIVE'];
    }
}
