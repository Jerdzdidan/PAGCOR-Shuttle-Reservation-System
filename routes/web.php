<?php

use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ShuttleRouteController;
use App\Http\Controllers\Admin\ShuttleScheduleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Employee\Auth\AuthenticatedSessionController as EmployeeAuthenticatedSessionController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\ReservationController as EmployeeReservationController;
use App\Http\Controllers\Employee\ScheduleController as EmployeeScheduleController;
use App\Http\Controllers\Employee\WaitlistController as EmployeeWaitlistController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
    Route::post('employees/{employee}/qr/regenerate', [EmployeeController::class, 'regenerateQr'])->name('employees.qr.regenerate');
    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('vehicles', VehicleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('drivers', DriverController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('routes', ShuttleRouteController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('schedules', ShuttleScheduleController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware('guest:employee')->prefix('employee')->name('employee.')->group(function () {
    Route::get('login', [EmployeeAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login/{employee}', [EmployeeAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:employee-login')
        ->name('login.store');
});

Route::middleware('auth:employee')->prefix('employee')->name('employee.')->group(function () {
    Route::get('dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');
    Route::get('schedules', [EmployeeScheduleController::class, 'index'])->name('schedules.index');
    Route::get('reservations', [EmployeeReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations', [EmployeeReservationController::class, 'store'])->name('reservations.store');
    Route::delete('reservations/{reservation}', [EmployeeReservationController::class, 'destroy'])->name('reservations.destroy');
    Route::post('waitlist', [EmployeeWaitlistController::class, 'store'])->name('waitlist.store');
    Route::delete('waitlist/{waitlistEntry}', [EmployeeWaitlistController::class, 'destroy'])->name('waitlist.destroy');
    Route::post('logout', [EmployeeAuthenticatedSessionController::class, 'destroy'])->name('logout');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
