<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = [
            ['name' => 'Maria Santos', 'email' => 'maria.santos@pagcor.example', 'contact_number' => '09171230001', 'department' => 'Human Resource and Development', 'position' => 'HR Manager'],
            ['name' => 'Jose Reyes', 'email' => 'jose.reyes@pagcor.example', 'contact_number' => '09171230002', 'department' => 'Information Technology', 'position' => 'IT Manager'],
            ['name' => 'Ana Cruz', 'email' => 'ana.cruz@pagcor.example', 'contact_number' => '09171230003', 'department' => 'Finance and Treasury', 'position' => 'Finance Analyst'],
            ['name' => 'Carlo Mendoza', 'email' => 'carlo.mendoza@pagcor.example', 'contact_number' => '09171230004', 'department' => 'Security and Surveillance', 'position' => 'Security Supervisor'],
            ['name' => 'Liza Garcia', 'email' => 'liza.garcia@pagcor.example', 'contact_number' => '09171230005', 'department' => 'Legal Services', 'position' => 'Legal Officer'],
            ['name' => 'Ramon Bautista', 'email' => 'ramon.bautista@pagcor.example', 'contact_number' => '09171230006', 'department' => 'Transportation Services', 'position' => 'Transport Coordinator'],
            ['name' => 'Elena Flores', 'email' => 'elena.flores@pagcor.example', 'contact_number' => '09171230007', 'department' => 'Corporate Services', 'position' => 'Administrative Officer'],
            ['name' => 'Miguel Navarro', 'email' => 'miguel.navarro@pagcor.example', 'contact_number' => '09171230008', 'department' => 'Information Technology', 'position' => 'Systems Administrator'],
            ['name' => 'Grace Villanueva', 'email' => 'grace.villanueva@pagcor.example', 'contact_number' => '09171230009', 'department' => 'Finance and Treasury', 'position' => 'Accountant'],
            ['name' => 'Paolo Aquino', 'email' => 'paolo.aquino@pagcor.example', 'contact_number' => '09171230010', 'department' => 'Corporate Services', 'position' => 'Procurement Officer'],
            ['name' => 'Teresa Morales', 'email' => 'teresa.morales@pagcor.example', 'contact_number' => '09171230011', 'department' => 'Gaming Licensing and Development', 'position' => 'Licensing Specialist'],
            ['name' => 'Adrian Lopez', 'email' => 'adrian.lopez@pagcor.example', 'contact_number' => '09171230012', 'department' => 'Corporate Services', 'position' => 'Records Officer'],
            ['name' => 'Roberto Dela Cruz', 'email' => 'roberto.delacruz@pagcor.example', 'contact_number' => '09181230001', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Eduardo Ramos', 'email' => 'eduardo.ramos@pagcor.example', 'contact_number' => '09181230002', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Antonio Castillo', 'email' => 'antonio.castillo@pagcor.example', 'contact_number' => '09181230003', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Noel Fernandez', 'email' => 'noel.fernandez@pagcor.example', 'contact_number' => '09181230004', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Jaime Torres', 'email' => 'jaime.torres@pagcor.example', 'contact_number' => '09181230005', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Danilo Mercado', 'email' => 'danilo.mercado@pagcor.example', 'contact_number' => '09181230006', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Manuel Salazar', 'email' => 'manuel.salazar@pagcor.example', 'contact_number' => '09181230007', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Renato Domingo', 'email' => 'renato.domingo@pagcor.example', 'contact_number' => '09181230008', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Victor Soriano', 'email' => 'victor.soriano@pagcor.example', 'contact_number' => '09181230009', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Alfredo Valdez', 'email' => 'alfredo.valdez@pagcor.example', 'contact_number' => '09181230010', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Benjamin Lim', 'email' => 'benjamin.lim@pagcor.example', 'contact_number' => '09181230011', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
            ['name' => 'Ricardo Pascual', 'email' => 'ricardo.pascual@pagcor.example', 'contact_number' => '09181230012', 'department' => 'Transportation Services', 'position' => 'Shuttle Driver'],
        ];

        foreach ($employees as $employee) {
            $employee['priority_status'] = in_array($employee['email'], [
                'maria.santos@pagcor.example',
                'ana.cruz@pagcor.example',
                'elena.flores@pagcor.example',
                'teresa.morales@pagcor.example',
            ], true) ? 'PRIORITY' : 'REGULAR';

            Employee::query()->updateOrCreate(
                ['email' => $employee['email']],
                $employee,
            );
        }
    }
}
