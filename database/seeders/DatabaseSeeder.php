<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::create([
        //     'name' => 'Root User',
        //     'email' => 'root@gmail.com',
        //     'password' => Hash::make('123456'),
        //     'user_type' => 'ADMIN',
        // ]);

        $seeders = [
            DepartmentSeeder::class,
            EmployeeSeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            // ShuttleRouteSeeder::class,
            // ShuttleScheduleSeeder::class,
        ];

        // if (! app()->isProduction()) {
        //     $seeders[] = ReportSimulationSeeder::class;
        // }

        $this->call($seeders);
    }
}
