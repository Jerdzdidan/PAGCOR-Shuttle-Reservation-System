<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['plate_number' => strtoupper(fake()->unique()->bothify('???-####')), 'vehicle_type' => fake()->randomElement(['Van', 'Bus', 'Minibus']), 'capacity' => fake()->numberBetween(20, 60), 'status' => 'ACTIVE', 'notes' => null];
    }
}
