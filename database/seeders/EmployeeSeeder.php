<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    private const ACTIVE_EMPLOYEE_COUNT = 420;

    private const INACTIVE_EMPLOYEE_COUNT = 12;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = collect([
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
        ]);

        for (
            $sequence = $employees->count() + 1;
            $sequence <= self::ACTIVE_EMPLOYEE_COUNT + self::INACTIVE_EMPLOYEE_COUNT;
            $sequence++
        ) {
            $employees->push($this->generatedEmployee($sequence));
        }

        foreach ($employees->values() as $index => $employee) {
            $sequence = $index + 1;
            $employee['priority_status'] = $this->isPriorityEmployee($sequence)
                ? Employee::PRIORITY_STATUS_PRIORITY
                : Employee::PRIORITY_STATUS_REGULAR;
            $employee['status'] = $sequence <= self::ACTIVE_EMPLOYEE_COUNT
                ? Employee::STATUS_ACTIVE
                : Employee::STATUS_INACTIVE;

            Employee::query()->updateOrCreate(
                ['email' => $employee['email']],
                $employee,
            );
        }
    }

    /**
     * @return array{
     *     name: string,
     *     email: string,
     *     contact_number: string,
     *     department: string,
     *     position: string
     * }
     */
    private function generatedEmployee(int $sequence): array
    {
        $firstNames = [
            'Adrian', 'Althea', 'Amado', 'Beatriz', 'Carmela', 'Dante',
            'Divina', 'Emilio', 'Esperanza', 'Felix', 'Gloria', 'Isabel',
            'Joaquin', 'Katrina', 'Leandro', 'Ligaya', 'Marcelo', 'Marites',
            'Nicanor', 'Nina', 'Orlando', 'Precious', 'Rafael', 'Rosario',
        ];
        $lastNames = [
            'Abad', 'Agbayani', 'Alonzo', 'Balagtas', 'Cabral', 'Dimaculangan',
            'Evangelista', 'Gatchalian', 'Hilario', 'Lacson', 'Macapagal',
            'Manalo', 'Panganiban', 'Samonte', 'Tolentino', 'Tuazon',
            'Villareal', 'Yabut',
        ];
        $departments = [
            ['department' => 'Human Resource and Development', 'position' => 'HR Specialist'],
            ['department' => 'Information Technology', 'position' => 'Systems Analyst'],
            ['department' => 'Finance and Treasury', 'position' => 'Accounting Specialist'],
            ['department' => 'Security and Surveillance', 'position' => 'Security Officer'],
            ['department' => 'Legal Services', 'position' => 'Legal Assistant'],
            ['department' => 'Corporate Services', 'position' => 'Administrative Assistant'],
            ['department' => 'Gaming Licensing and Development', 'position' => 'Licensing Associate'],
            ['department' => 'Internal Audit', 'position' => 'Audit Associate'],
            ['department' => 'Procurement Services', 'position' => 'Procurement Associate'],
            ['department' => 'Corporate Communications', 'position' => 'Communications Associate'],
            ['department' => 'Responsible Gaming', 'position' => 'Program Officer'],
            ['department' => 'Regulatory Compliance', 'position' => 'Compliance Analyst'],
        ];
        $zeroBasedSequence = $sequence - 1;
        $department = $departments[$zeroBasedSequence % count($departments)];

        return [
            'name' => $firstNames[$zeroBasedSequence % count($firstNames)]
                .' '
                .$lastNames[intdiv($zeroBasedSequence, count($firstNames)) % count($lastNames)],
            'email' => sprintf('employee.%04d@pagcor.example', $sequence),
            'contact_number' => sprintf('0921%07d', $sequence),
            'department' => $department['department'],
            'position' => $department['position'],
        ];
    }

    private function isPriorityEmployee(int $sequence): bool
    {
        return in_array($sequence % 20, [1, 3, 7], true)
            || $sequence === 11;
    }
}
