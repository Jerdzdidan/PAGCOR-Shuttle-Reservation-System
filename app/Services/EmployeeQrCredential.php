<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\URL;

final class EmployeeQrCredential
{
    public function loginUrl(Employee $employee): string
    {
        $employee->ensureEmployeeCode();

        return URL::signedRoute(
            name: 'employee.login.qr',
            parameters: [
                'employeeCode' => $employee->employee_code,
            ],
            absolute: false,
        );
    }
}
