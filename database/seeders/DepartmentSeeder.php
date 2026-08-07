<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Departments that intentionally carry no employees so the delete flow and
     * the "no employees" empty states can be exercised.
     *
     * @var list<string>
     */
    public const UNSTAFFED_DEPARTMENTS = [
        'Office of the Chairman',
        'Special Projects',
    ];

    /**
     * Departments with employees assigned to them.
     *
     * @return list<string>
     */
    public static function staffedDepartments(): array
    {
        return [
            'Corporate Communications',
            'Corporate Services',
            'Finance and Treasury',
            'Gaming Licensing and Development',
            'Human Resource and Development',
            'Information Technology',
            'Internal Audit',
            'Legal Services',
            'Procurement Services',
            'Regulatory Compliance',
            'Responsible Gaming',
            'Security and Surveillance',
            'Transportation Services',
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ...self::staffedDepartments(),
            ...self::UNSTAFFED_DEPARTMENTS,
        ];

        foreach ($departments as $department) {
            Department::query()->firstOrCreate(['name' => $department]);
        }
    }
}
