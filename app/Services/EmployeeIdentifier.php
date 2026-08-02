<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Collection;

class EmployeeIdentifier
{
    public const PATTERN = '/^\d{2}-\d{5}$/';

    public function resolve(string $employeeCode): ?Employee
    {
        $employeeCode = trim($employeeCode);

        if (preg_match(self::PATTERN, $employeeCode) !== 1) {
            return null;
        }

        return Employee::query()
            ->where('employee_code', $employeeCode)
            ->first();
    }

    public function assignMissing(): void
    {
        Employee::query()
            ->whereNull('employee_code')
            ->orderBy('employee_id')
            ->chunkById(500, function (Collection $employees): void {
                $employees->each->ensureEmployeeCode();
            }, 'employee_id');
    }
}
