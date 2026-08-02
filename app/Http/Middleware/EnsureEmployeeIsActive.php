<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user('employee');

        if ($employee instanceof Employee && ! $employee->isActive()) {
            Auth::guard('employee')->logout();
            $request->session()->forget('url.intended');
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            return to_route('employee.login')->with(
                'status',
                'Your employee access is inactive. Contact Shuttle Operations for assistance.',
            );
        }

        return $next($request);
    }
}
