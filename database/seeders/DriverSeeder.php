<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = [
            ['name' => 'Roberto Dela Cruz', 'email' => 'roberto.delacruz@pagcor.example'],
            ['name' => 'Eduardo Ramos', 'email' => 'eduardo.ramos@pagcor.example'],
            ['name' => 'Antonio Castillo', 'email' => 'antonio.castillo@pagcor.example'],
            ['name' => 'Noel Fernandez', 'email' => 'noel.fernandez@pagcor.example'],
            ['name' => 'Jaime Torres', 'email' => 'jaime.torres@pagcor.example'],
            ['name' => 'Danilo Mercado', 'email' => 'danilo.mercado@pagcor.example'],
            ['name' => 'Manuel Salazar', 'email' => 'manuel.salazar@pagcor.example'],
            ['name' => 'Renato Domingo', 'email' => 'renato.domingo@pagcor.example'],
            ['name' => 'Victor Soriano', 'email' => 'victor.soriano@pagcor.example'],
            ['name' => 'Alfredo Valdez', 'email' => 'alfredo.valdez@pagcor.example'],
            ['name' => 'Benjamin Lim', 'email' => 'benjamin.lim@pagcor.example'],
            ['name' => 'Ricardo Pascual', 'email' => 'ricardo.pascual@pagcor.example'],
            ['name' => 'Andres Villareal', 'email' => 'andres.villareal@pagcor.example'],
            ['name' => 'Cesar Manalo', 'email' => 'cesar.manalo@pagcor.example'],
            ['name' => 'Domingo Evangelista', 'email' => 'domingo.evangelista@pagcor.example'],
            ['name' => 'Ernesto Macapagal', 'email' => 'ernesto.macapagal@pagcor.example'],
            ['name' => 'Felipe Tolentino', 'email' => 'felipe.tolentino@pagcor.example'],
            ['name' => 'Gregorio Abad', 'email' => 'gregorio.abad@pagcor.example'],
            ['name' => 'Hernando Yabut', 'email' => 'hernando.yabut@pagcor.example'],
            ['name' => 'Isagani Laurel', 'email' => 'isagani.laurel@pagcor.example'],
            ['name' => 'Joaquin Panganiban', 'email' => 'joaquin.panganiban@pagcor.example'],
            ['name' => 'Leopoldo Tuazon', 'email' => 'leopoldo.tuazon@pagcor.example'],
            ['name' => 'Maximo Samonte', 'email' => 'maximo.samonte@pagcor.example'],
            ['name' => 'Nestor Cabral', 'email' => 'nestor.cabral@pagcor.example'],
        ];
        $today = CarbonImmutable::now(
            (string) config('shuttle.operating_timezone', 'Asia/Manila')
        )->startOfDay();

        foreach ($drivers as $index => $driverData) {
            $sequence = $index + 1;
            $contactNumber = sprintf('0918%07d', 1230000 + $sequence);
            $employee = Employee::query()->updateOrCreate(
                ['email' => $driverData['email']],
                [
                    'name' => $driverData['name'],
                    'contact_number' => $contactNumber,
                    'department' => 'Transportation Services',
                    'position' => 'Shuttle Driver',
                    'priority_status' => Employee::PRIORITY_STATUS_REGULAR,
                    'status' => Employee::STATUS_ACTIVE,
                ],
            );

            Driver::query()->updateOrCreate(
                ['employee_id' => (string) $employee->employee_id],
                [
                    'name' => $employee->name,
                    'contact_number' => $contactNumber,
                    'license_number' => sprintf('N01-18-%06d', 100000 + $sequence),
                    'license_expires_at' => $today
                        ->addYears(2)
                        ->addMonths(($sequence - 1) % 12)
                        ->addDays(($sequence * 3) % 20)
                        ->toDateString(),
                    'status' => 'ACTIVE',
                    'notes' => 'Assigned to PAGCOR employee shuttle service.',
                ],
            );
        }
    }
}
