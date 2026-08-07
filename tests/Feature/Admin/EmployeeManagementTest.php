<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function employeeAdmin(): User
{
    return User::factory()->create(['user_type' => 'ADMIN']);
}

test('created employees are always active regardless of the submitted status', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)->post('/admin/employees', [
        'name' => 'Nina Cruz',
        'email' => 'nina.cruz@example.com',
        'contact_number' => '09000000000',
        'position' => 'Analyst',
        'priority_status' => 'REGULAR',
        'status' => 'INACTIVE',
    ])->assertSessionHasNoErrors();

    $employee = Employee::where('email', 'nina.cruz@example.com')->firstOrFail();
    expect($employee->status)->toBe(Employee::STATUS_ACTIVE);
});

test('employee status can be changed on update', function () {
    $admin = employeeAdmin();
    $employee = Employee::create([
        'name' => 'Nina Cruz',
        'email' => 'nina.cruz@example.com',
        'priority_status' => Employee::PRIORITY_STATUS_REGULAR,
    ]);

    $this->actingAs($admin)->put('/admin/employees/'.$employee->employee_id, [
        'name' => 'Nina Cruz',
        'email' => 'nina.cruz@example.com',
        'priority_status' => 'REGULAR',
        'status' => 'INACTIVE',
    ])->assertSessionHasNoErrors();

    expect($employee->fresh()->status)->toBe(Employee::STATUS_INACTIVE);
});

test('imported employees are always active even when the file supplies a status', function () {
    $admin = employeeAdmin();
    $csv = "name,email,priority_status,status\nNina Cruz,nina.cruz@example.com,REGULAR,INACTIVE\n";

    $this->actingAs($admin)->post('/admin/employees/import', [
        'file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
    ])->assertSessionHasNoErrors();

    $employee = Employee::where('email', 'nina.cruz@example.com')->firstOrFail();
    expect($employee->status)->toBe(Employee::STATUS_ACTIVE);
});
