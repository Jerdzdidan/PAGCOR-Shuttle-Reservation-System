<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeReservationData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, EmployeeReservationData $data): Response
    {
        return Inertia::render(
            'employee/dashboard',
            $data->dashboard($this->employee($request))
        );
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user('employee');

        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }
}
