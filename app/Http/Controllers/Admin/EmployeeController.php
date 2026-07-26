<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportEmployeesRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Imports\EmployeesImport;
use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleWaitlistEntry;
use App\Services\EmployeeQrCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Throwable;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(EmployeeQrCredential $qrCredential): Response
    {
        return Inertia::render('admin/employees', [
            'employees' => Employee::query()
                ->select([
                    'employee_id',
                    'name',
                    'email',
                    'contact_number',
                    'department',
                    'position',
                    'priority_status',
                    'qr_token_version',
                    'created_at',
                ])
                ->latest('employee_id')
                ->get()
                ->map(fn (Employee $employee): array => [
                    ...$employee->only([
                        'employee_id',
                        'name',
                        'email',
                        'contact_number',
                        'department',
                        'position',
                        'priority_status',
                        'created_at',
                    ]),
                    'qr_login_url' => $qrCredential->loginUrl($employee),
                ]),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): never
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::create($request->validated());

        return to_route('admin.employees.index')->with('success', 'Employee created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): never
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee): never
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validated();

        if (
            $employee->isPriority()
            && $validated['priority_status'] === Employee::PRIORITY_STATUS_REGULAR
            && $employee->shuttleReservations()
                ->whereDate('travel_date', '>=', today(config('shuttle.operating_timezone')))
                ->where('seat_number', '<=', max(0, (int) config('shuttle.priority_seat_count', 8)))
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'priority_status' => 'Cancel this employee’s protected-seat reservations before changing them to regular status.',
            ]);
        }

        $employee->update($validated);

        return to_route('admin.employees.index')->with('success', 'Employee updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $hasActiveTravel = ShuttleReservation::query()
            ->where('employee_id', $employee->employee_id)
            ->exists()
            || ShuttleWaitlistEntry::query()
                ->where('employee_id', $employee->employee_id)
                ->exists();

        if ($hasActiveTravel) {
            throw ValidationException::withMessages([
                'employee' => 'Cancel this employee’s reservations and waitlist entries before deleting the record.',
            ]);
        }

        $employee->delete();

        return to_route('admin.employees.index')->with('success', 'Employee deleted successfully.');
    }

    public function import(ImportEmployeesRequest $request): RedirectResponse
    {
        try {
            Excel::import(new EmployeesImport, $request->file('file'));
        } catch (ExcelValidationException $exception) {
            $message = collect($exception->failures())
                ->take(5)
                ->map(fn (Failure $failure): string => 'Row '.$failure->row().': '.implode(' ', $failure->errors()))
                ->implode(' ');

            throw ValidationException::withMessages([
                'file' => $message ?: 'The employee file contains invalid rows.',
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'The employee file could not be imported. Check the file format and headings, then try again.',
            ]);
        }

        return to_route('admin.employees.index')->with('success', 'Employees imported successfully.');
    }

    public function regenerateQr(Employee $employee, EmployeeQrCredential $qrCredential): RedirectResponse
    {
        $qrCredential->regenerate($employee);

        return to_route('admin.employees.index')->with('success', 'Employee QR code regenerated successfully.');
    }
}
