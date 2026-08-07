<?php

namespace App\Http\Controllers\Employee\Auth;

use App\Enums\EmployeeLoginMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\Auth\EmployeeCodeLoginRequest;
use App\Http\Requests\Employee\Auth\EmployeeQrLoginRequest;
use App\Models\Employee;
use App\Models\EmployeeLoginLog;
use App\Services\EmployeeIdentifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('employee/login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function storeQr(
        EmployeeQrLoginRequest $request,
        EmployeeIdentifier $identifiers,
    ): RedirectResponse {
        $employee = $identifiers->resolve((string) $request->route('employeeCode'));

        if ($employee === null) {
            throw ValidationException::withMessages([
                'credential' => 'This employee QR code is invalid.',
            ]);
        }

        return $this->authenticate($request, $employee, 'credential', EmployeeLoginMethod::QrScan);
    }

    public function storeCode(
        EmployeeCodeLoginRequest $request,
        EmployeeIdentifier $identifiers,
    ): RedirectResponse {
        $employee = $identifiers->resolve($request->string('employee_code')->toString());

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_code' => 'The employee ID is invalid or inactive.',
            ]);
        }

        return $this->authenticate($request, $employee, 'employee_code', EmployeeLoginMethod::EmployeeCode);
    }

    private function authenticate(
        Request $request,
        Employee $employee,
        string $errorKey,
        EmployeeLoginMethod $loginMethod,
    ): RedirectResponse {
        $authenticatedEmployee = DB::transaction(function () use ($employee, $errorKey, $loginMethod): Employee {
            $lockedEmployee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->getKey());

            if (! $lockedEmployee->isActive()) {
                throw ValidationException::withMessages([
                    $errorKey => $errorKey === 'credential'
                        ? 'This employee account is inactive. Contact Shuttle Operations for assistance.'
                        : 'The employee ID is invalid or inactive.',
                ]);
            }

            EmployeeLoginLog::query()->create([
                'employee_id' => $lockedEmployee->getKey(),
                'employee_id_snapshot' => $lockedEmployee->getKey(),
                'employee_code_snapshot' => $lockedEmployee->employee_code,
                'employee_name' => $lockedEmployee->name,
                'department' => $lockedEmployee->department,
                'priority_status' => $lockedEmployee->priority_status,
                'login_method' => $loginMethod,
                'logged_in_at' => now(),
            ]);

            return $lockedEmployee;
        }, 5);

        Auth::guard('employee')->login($authenticatedEmployee);

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->to(route('employee.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('employee')->logout();

        $request->session()->forget('url.intended');
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return to_route('employee.login');
    }
}
