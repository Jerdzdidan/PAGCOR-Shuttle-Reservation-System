<?php

use App\Enums\UserLogAction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;

function userLogAdmin(): User
{
    return User::factory()->create(['user_type' => 'ADMIN', 'name' => 'Olivia Ramirez']);
}

test('guests and non-administrators cannot open the user logs', function () {
    $this->get('/admin/user-logs')->assertRedirect('/login');

    $this->actingAs(User::factory()->create(['user_type' => 'EMPLOYEE']))
        ->get('/admin/user-logs')
        ->assertForbidden();
});

test('administrators can open the user logs', function () {
    $this->actingAs(userLogAdmin())
        ->get('/admin/user-logs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/user-logs')->has('logs.data'));
});

test('updating a record through the admin screens is recorded with both sides of the change', function () {
    $admin = userLogAdmin();
    $vehicle = Vehicle::create([
        'plate_number' => 'ABC-1234',
        'vehicle_type' => 'Bus',
        'capacity' => 40,
        'status' => 'ACTIVE',
    ]);
    UserLog::query()->delete();

    $this->actingAs($admin)->put('/admin/vehicles/'.$vehicle->id, [
        'plate_number' => 'ABC-1234',
        'vehicle_type' => 'Bus',
        'capacity' => 44,
        'status' => 'MAINTENANCE',
        'notes' => 'Grounded for repair',
    ])->assertSessionHasNoErrors();

    $log = UserLog::query()->latest('id')->firstOrFail();

    expect($log->action)->toBe(UserLogAction::Updated)
        ->and($log->subject_type)->toBe('Vehicle')
        ->and($log->subject_id)->toBe($vehicle->id)
        ->and($log->subject_label)->toBe('ABC-1234')
        ->and($log->user_name)->toBe('Olivia Ramirez')
        ->and($log->user_id_snapshot)->toBe($admin->id)
        ->and($log->after_values)->toMatchArray(['capacity' => 44, 'status' => 'MAINTENANCE'])
        ->and($log->before_values)->toMatchArray(['capacity' => 40, 'status' => 'ACTIVE']);
});

test('deleting a record keeps what was lost', function () {
    $admin = userLogAdmin();
    $department = Department::create(['name' => 'Special Projects']);
    UserLog::query()->delete();

    $this->actingAs($admin)
        ->delete('/admin/departments/'.$department->id)
        ->assertSessionHasNoErrors();

    $log = UserLog::query()->latest('id')->firstOrFail();

    expect($log->action)->toBe(UserLogAction::Deleted)
        ->and($log->subject_type)->toBe('Department')
        ->and($log->subject_label)->toBe('Special Projects')
        ->and($log->before_values)->toMatchArray(['name' => 'Special Projects'])
        ->and($log->after_values)->toBeNull();
});

test('employee changes are recorded without ever storing a password', function () {
    $admin = userLogAdmin();
    $employee = Employee::create([
        'name' => 'Nina Cruz',
        'email' => 'nina.cruz@example.com',
        'priority_status' => Employee::PRIORITY_STATUS_REGULAR,
    ]);
    UserLog::query()->delete();

    $this->actingAs($admin)->put('/admin/employees/'.$employee->employee_id, [
        'name' => 'Nina Cruz',
        'email' => 'nina.cruz@example.com',
        'priority_status' => 'PRIORITY',
        'status' => 'ACTIVE',
    ])->assertSessionHasNoErrors();

    $log = UserLog::query()->latest('id')->firstOrFail();

    expect($log->subject_type)->toBe('Employee')
        ->and($log->subject_label)->toBe('Nina Cruz')
        ->and($log->after_values)->toMatchArray(['priority_status' => 'PRIORITY'])
        ->and(array_keys($log->after_values ?? []))->not->toContain('password')
        ->and(array_keys($log->after_values ?? []))->not->toContain('updated_at');
});

test('changes made outside a signed-in session are not recorded', function () {
    UserLog::query()->delete();

    Vehicle::create([
        'plate_number' => 'ZZZ-9999',
        'vehicle_type' => 'Van',
        'capacity' => 15,
        'status' => 'ACTIVE',
    ])->update(['capacity' => 16]);

    expect(UserLog::query()->count())->toBe(0);
});

test('a save that changes nothing leaves no entry', function () {
    $admin = userLogAdmin();
    $vehicle = Vehicle::create([
        'plate_number' => 'DEF-5678',
        'vehicle_type' => 'Van',
        'capacity' => 15,
        'status' => 'ACTIVE',
    ]);
    UserLog::query()->delete();

    $this->actingAs($admin)->put('/admin/vehicles/'.$vehicle->id, [
        'plate_number' => 'DEF-5678',
        'vehicle_type' => 'Van',
        'capacity' => 15,
        'status' => 'ACTIVE',
        'notes' => '',
    ])->assertSessionHasNoErrors();

    expect(UserLog::query()->count())->toBe(0);
});

test('user log records cannot be amended or removed', function () {
    $admin = userLogAdmin();
    $log = UserLog::query()->create([
        'user_id' => $admin->id,
        'user_id_snapshot' => $admin->id,
        'user_name' => $admin->name,
        'user_email' => $admin->email,
        'action' => UserLogAction::Updated,
        'subject_type' => 'Vehicle',
        'subject_id' => 1,
        'subject_label' => 'ABC-1234',
        'before_values' => ['status' => 'ACTIVE'],
        'after_values' => ['status' => 'INACTIVE'],
        'occurred_at' => now(),
    ]);

    expect(fn () => $log->update(['subject_label' => 'Tampered']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
});

test('the log can be filtered by action, record type and user', function () {
    $admin = userLogAdmin();
    $other = User::factory()->create(['user_type' => 'ADMIN', 'name' => 'Dennis Aguilar']);

    foreach ([
        [$admin, UserLogAction::Updated, 'Vehicle', 'ABC-1234'],
        [$admin, UserLogAction::Deleted, 'Driver', 'Roberto Dela Cruz'],
        [$other, UserLogAction::Updated, 'Vehicle', 'XYZ-9876'],
    ] as [$actor, $action, $type, $label]) {
        UserLog::query()->create([
            'user_id' => $actor->id,
            'user_id_snapshot' => $actor->id,
            'user_name' => $actor->name,
            'user_email' => $actor->email,
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => 1,
            'subject_label' => $label,
            'occurred_at' => now(),
        ]);
    }

    $this->actingAs($admin)
        ->get('/admin/user-logs?action=UPDATED&subject_type=Vehicle&user_id='.$admin->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/user-logs')
            ->has('logs.data', 1)
            ->where('logs.data.0.subject_label', 'ABC-1234')
        );

    $this->actingAs($admin)
        ->get('/admin/user-logs?search=Roberto')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'DELETED')
        );
});
