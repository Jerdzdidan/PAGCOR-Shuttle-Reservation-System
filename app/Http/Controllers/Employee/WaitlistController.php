<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\JoinWaitlistRequest;
use App\Http\Requests\Employee\WithdrawWaitlistRequest;
use App\Models\Employee;
use App\Models\ShuttleWaitlistEntry;
use App\Services\EmployeeReservationService;
use Illuminate\Http\RedirectResponse;

class WaitlistController extends Controller
{
    public function store(
        JoinWaitlistRequest $request,
        EmployeeReservationService $service,
    ): RedirectResponse {
        $validated = $request->validated();
        $employee = $request->user('employee');

        abort_unless($employee instanceof Employee, 403);

        $service->joinWaitlist(
            $employee,
            (int) $validated['schedule_id'],
            $validated['travel_date'],
        );

        return back()->with('success', 'You joined the waitlist.');
    }

    public function destroy(
        WithdrawWaitlistRequest $request,
        ShuttleWaitlistEntry $waitlistEntry,
        EmployeeReservationService $service,
    ): RedirectResponse {
        $employee = $request->user('employee');

        abort_unless($employee instanceof Employee, 403);

        $service->withdraw($employee, $waitlistEntry);

        return back()->with('success', 'You left the waitlist.');
    }
}
