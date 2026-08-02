<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\FinishedServiceController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ServiceAttendanceController;
use App\Http\Controllers\Admin\ServiceCloseoutController;
use App\Http\Controllers\Admin\ShuttleRouteController;
use App\Http\Controllers\Admin\ShuttleScheduleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Employee\Auth\AuthenticatedSessionController as EmployeeAuthenticatedSessionController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\ReservationController as EmployeeReservationController;
use App\Http\Controllers\Employee\ScheduleController as EmployeeScheduleController;
use App\Http\Controllers\Employee\WaitlistController as EmployeeWaitlistController;
use App\Services\AdminReportService;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'admin'])
    ->get('dashboard', DashboardController::class)
    ->name('dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
    Route::post('employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('finished-services', [FinishedServiceController::class, 'index'])->name('finished_services.index');
    Route::get('finished-services/{serviceOccurrence}', [FinishedServiceController::class, 'show'])->name('finished_services.show');
    Route::post('finished-services/{serviceOccurrence}/attendance/scan', [ServiceAttendanceController::class, 'scan'])
        ->name('finished_services.attendance.scan');
    Route::post('finished-services/{serviceOccurrence}/attendance/employee-code', [ServiceAttendanceController::class, 'recordByEmployeeCode'])
        ->name('finished_services.attendance.employee_code');
    Route::patch('finished-services/{serviceOccurrence}/attendance/{reservation}', [ServiceAttendanceController::class, 'update'])
        ->name('finished_services.attendance.update');
    Route::post('finished-services/{serviceOccurrence}/attendance/mark-all', [ServiceAttendanceController::class, 'markAll'])
        ->name('finished_services.attendance.mark_all');
    Route::post('finished-services/{serviceOccurrence}/complete', [ServiceCloseoutController::class, 'complete'])
        ->name('finished_services.complete');
    Route::post('finished-services/{serviceOccurrence}/not-operated', [ServiceCloseoutController::class, 'notOperated'])
        ->name('finished_services.not_operated');
    Route::patch('finished-services/{serviceOccurrence}/correction', [ServiceCloseoutController::class, 'correct'])
        ->name('finished_services.correct');
    Route::post('finished-services/{serviceOccurrence}/reopen', [ServiceCloseoutController::class, 'reopen'])
        ->name('finished_services.reopen');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{reportSlug}/export', [ReportController::class, 'export'])
        ->whereIn('reportSlug', AdminReportService::reportSlugs())
        ->name('reports.export');
    Route::get('reports/{reportSlug}', [ReportController::class, 'show'])
        ->whereIn('reportSlug', AdminReportService::reportSlugs())
        ->name('reports.show');
    Route::resource('vehicles', VehicleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('drivers', DriverController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('routes', ShuttleRouteController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('schedules', ShuttleScheduleController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware('guest:employee')->prefix('employee')->name('employee.')->group(function () {
    Route::get('login', [EmployeeAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [EmployeeAuthenticatedSessionController::class, 'storeCode'])
        ->middleware('throttle:employee-login')
        ->name('login.code');
    Route::post('login/qr/{employeeCode}', [EmployeeAuthenticatedSessionController::class, 'storeQr'])
        ->where('employeeCode', '[0-9]{2}-[0-9]{5}')
        ->middleware('throttle:employee-login')
        ->name('login.qr');
});

Route::middleware(['auth:employee', 'employee.active'])->prefix('employee')->name('employee.')->group(function () {
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
