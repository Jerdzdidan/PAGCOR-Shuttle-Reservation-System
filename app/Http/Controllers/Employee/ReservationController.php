<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CancelReservationRequest;
use App\Http\Requests\Employee\StoreReservationRequest;
use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Services\EmployeeReservationData;
use App\Services\EmployeeReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request, EmployeeReservationData $data): Response
    {
        return Inertia::render(
            'employee/reservations',
            $data->reservations($this->employee($request))
        );
    }

    public function store(
        StoreReservationRequest $request,
        EmployeeReservationService $service,
    ): RedirectResponse {
        $validated = $request->validated();

        $service->reserve(
            $this->employee($request),
            (int) $validated['schedule_id'],
            $validated['travel_date'],
            (int) $validated['seat_number'],
        );

        return back()->with('success', 'Your seat has been reserved.');
    }

    public function destroy(
        CancelReservationRequest $request,
        ShuttleReservation $reservation,
        EmployeeReservationService $service,
    ): RedirectResponse {
        $promotedReservation = $service->cancel(
            $this->employee($request),
            $reservation,
        );
        $message = $promotedReservation === null
            ? 'Your reservation has been cancelled.'
            : 'Your reservation has been cancelled and the seat was assigned to the next employee.';

        return back()->with('success', $message);
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user('employee');

        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }
}
