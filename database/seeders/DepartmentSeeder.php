<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
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

        foreach ($departments as $department) {
            Department::query()->firstOrCreate(['name' => $department]);
        }
    }
}
