<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\URL;

final class EmployeeQrCredential
{
    public function loginUrl(Employee $employee): string
    {
        return URL::signedRoute(
            name: 'employee.login.store',
            parameters: [
                'employee' => $employee->getRouteKey(),
                'version' => $employee->qr_token_version,
            ],
            absolute: false,
        );
    }

    public function regenerate(Employee $employee): Employee
    {
        $employee->increment('qr_token_version');

        return $employee->refresh();
    }
}
