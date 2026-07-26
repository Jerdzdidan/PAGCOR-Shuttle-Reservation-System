<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guests are redirected from user management', function () {
    $this->get('/admin/users')->assertRedirect('/login');
});

test('employees cannot access user management', function () {
    $this->actingAs(User::factory()->create(['user_type' => 'EMPLOYEE']))
        ->get('/admin/users')
        ->assertForbidden();
});

test('administrators can view user management', function () {
    $this->actingAs(User::factory()->create(['user_type' => 'ADMIN']))
        ->get('/admin/users')
        ->assertOk();
});

test('administrators can create users', function () {
    $admin = User::factory()->create(['user_type' => 'ADMIN']);

    $response = $this->actingAs($admin)->post('/admin/users', [
        'name' => 'New Employee',
        'email' => 'new.employee@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
        'user_type' => 'EMPLOYEE',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index', absolute: false));
    $user = User::where('email', 'new.employee@example.com')->firstOrFail();

    expect($user->name)->toBe('New Employee')
        ->and($user->user_type)->toBe('EMPLOYEE')
        ->and(Hash::check('secure-password', $user->password))->toBeTrue();
});

test('administrators can update users without changing their password', function () {
    $admin = User::factory()->create(['user_type' => 'ADMIN']);
    $user = User::factory()->create([
        'email' => 'employee@example.com',
        'user_type' => 'EMPLOYEE',
    ]);
    $existingPassword = $user->password;

    $response = $this->actingAs($admin)->put('/admin/users/'.$user->id, [
        'name' => 'Updated Employee',
        'email' => 'updated.employee@example.com',
        'password' => '',
        'password_confirmation' => '',
        'user_type' => 'ADMIN',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index', absolute: false));
    $user->refresh();

    expect($user->name)->toBe('Updated Employee')
        ->and($user->email)->toBe('updated.employee@example.com')
        ->and($user->user_type)->toBe('ADMIN')
        ->and($user->password)->toBe($existingPassword)
        ->and($user->email_verified_at)->toBeNull();
});

test('administrators can delete other users', function () {
    $admin = User::factory()->create(['user_type' => 'ADMIN']);
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->delete('/admin/users/'.$user->id);

    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index', absolute: false));
    expect($user->fresh())->toBeNull();
});

test('administrators cannot delete their own account from user management', function () {
    $admin = User::factory()->create(['user_type' => 'ADMIN']);

    $response = $this->actingAs($admin)->delete('/admin/users/'.$admin->id);

    $response->assertSessionHasErrors('user');
    expect($admin->fresh())->not->toBeNull();
});
