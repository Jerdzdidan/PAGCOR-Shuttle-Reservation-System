<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = [
            ['email' => 'roberto.delacruz@pagcor.example', 'license_number' => 'N01-18-100001', 'license_expires_at' => '2028-03-15'],
            ['email' => 'eduardo.ramos@pagcor.example', 'license_number' => 'N01-18-100002', 'license_expires_at' => '2028-05-20'],
            ['email' => 'antonio.castillo@pagcor.example', 'license_number' => 'N01-18-100003', 'license_expires_at' => '2028-07-08'],
            ['email' => 'noel.fernandez@pagcor.example', 'license_number' => 'N01-18-100004', 'license_expires_at' => '2028-09-12'],
            ['email' => 'jaime.torres@pagcor.example', 'license_number' => 'N01-18-100005', 'license_expires_at' => '2029-01-18'],
            ['email' => 'danilo.mercado@pagcor.example', 'license_number' => 'N01-18-100006', 'license_expires_at' => '2029-02-22'],
            ['email' => 'manuel.salazar@pagcor.example', 'license_number' => 'N01-18-100007', 'license_expires_at' => '2029-04-30'],
            ['email' => 'renato.domingo@pagcor.example', 'license_number' => 'N01-18-100008', 'license_expires_at' => '2029-06-14'],
            ['email' => 'victor.soriano@pagcor.example', 'license_number' => 'N01-18-100009', 'license_expires_at' => '2029-08-19'],
            ['email' => 'alfredo.valdez@pagcor.example', 'license_number' => 'N01-18-100010', 'license_expires_at' => '2029-10-25'],
            ['email' => 'benjamin.lim@pagcor.example', 'license_number' => 'N01-18-100011', 'license_expires_at' => '2030-01-11'],
            ['email' => 'ricardo.pascual@pagcor.example', 'license_number' => 'N01-18-100012', 'license_expires_at' => '2030-03-17'],
        ];

        foreach ($drivers as $driverData) {
            $employee = Employee::query()
                ->where('email', $driverData['email'])
                ->firstOrFail();

            Driver::query()->updateOrCreate(
                ['employee_id' => (string) $employee->employee_id],
                [
                    'name' => $employee->name,
                    'contact_number' => $employee->contact_number,
                    'license_number' => $driverData['license_number'],
                    'license_expires_at' => $driverData['license_expires_at'],
                    'status' => 'ACTIVE',
                    'notes' => 'Assigned to PAGCOR employee shuttle service.',
                ],
            );
        }
    }
}
