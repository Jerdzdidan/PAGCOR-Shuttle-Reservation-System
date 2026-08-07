<?php

use App\Http\Middleware\EnsureBookingWindowIsOpen;
use App\Http\Middleware\EnsureEmployeeIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'employee.active' => EnsureEmployeeIsActive::class,
            'booking.window' => EnsureBookingWindowIsOpen::class,
        ]);
        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('employee', 'employee/*') ? '/employee/login' : '/login',
        );
        $middleware->redirectUsersTo(
            fn (Request $request): string => $request->is('employee', 'employee/*') ? '/employee/dashboard' : '/dashboard',
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
