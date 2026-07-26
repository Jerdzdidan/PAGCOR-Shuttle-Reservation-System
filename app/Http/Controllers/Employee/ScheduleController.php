<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\BrowseSchedulesRequest;
use App\Models\Employee;
use App\Services\EmployeeReservationData;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(
        BrowseSchedulesRequest $request,
        EmployeeReservationData $data,
    ): Response {
        $employee = $request->user('employee');

        abort_unless($employee instanceof Employee, 403);

        return Inertia::render(
            'employee/schedules',
            $data->scheduleBrowser($employee, $request->validated())
        );
    }
}
