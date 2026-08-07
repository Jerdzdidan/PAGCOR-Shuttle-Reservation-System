<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $this->forgetEmployeeIntendedUrl($request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Drops an intended URL that belongs to the employee portal.
     *
     * Visiting an employee page while signed out parks that URL in the session.
     * Signing in here authenticates the `web` guard only, so following it would
     * bounce a perfectly valid administrator straight back to the employee
     * sign-in page.
     */
    private function forgetEmployeeIntendedUrl(Request $request): void
    {
        $intendedUrl = $request->session()->get('url.intended');

        if (! is_string($intendedUrl)) {
            return;
        }

        $path = trim((string) parse_url($intendedUrl, PHP_URL_PATH), '/');

        if ($path === 'employee' || str_starts_with($path, 'employee/')) {
            $request->session()->forget('url.intended');
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget('url.intended');
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
