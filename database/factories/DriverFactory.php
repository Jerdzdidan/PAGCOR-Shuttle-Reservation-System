<?php

namespace Database\Factories;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->name(), 'employee_id' => 'DRV-'.fake()->unique()->numerify('####'), 'contact_number' => fake()->numerify('09#########'), 'license_number' => 'LIC-'.fake()->unique()->numerify('######'), 'license_expires_at' => now()->addYear()->toDateString(), 'status' => 'ACTIVE', 'notes' => null];
    }
}
