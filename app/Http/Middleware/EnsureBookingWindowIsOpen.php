<?php

namespace App\Http\Middleware;

use App\Services\EmployeeBookingWindow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBookingWindowIsOpen
{
    public function __construct(private EmployeeBookingWindow $bookingWindow) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->bookingWindow->isOpen()) {
            return $next($request);
        }

        $message = $this->bookingWindow->message();

        if ($request->isMethod('GET')) {
            return to_route('employee.dashboard')->with('error', $message);
        }

        return back()->withErrors(['schedule_id' => $message]);
    }
}
