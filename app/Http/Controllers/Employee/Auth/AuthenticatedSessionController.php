<?php

namespace App\Http\Controllers\Employee\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\Auth\EmployeeQrLoginRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function store(EmployeeQrLoginRequest $request, Employee $employee): RedirectResponse
    {
        Auth::guard('employee')->login($employee);

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->to(route('employee.dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('employee')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('employee.login');
    }
}
