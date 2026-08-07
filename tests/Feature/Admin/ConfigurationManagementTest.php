<?php

use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

function configurationAdmin(): User
{
    return User::factory()->create(['user_type' => 'ADMIN']);
}

function schedulePayload(Vehicle $vehicle, Driver $driver, ShuttleRoute $route): array
{
    return [
        'route_id' => $route->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'direction' => 'OUTBOUND',
        'departure_time' => '07:30',
        'operating_days' => ['monday', 'wednesday'],
        'effective_from' => now()->toDateString(),
        'effective_until' => '',
        'capacity_override' => '',
        'waitlist_enabled' => false,
        'notes' => 'Morning shuttle',
    ];
}

test('guests are redirected and employees are forbidden from configuration', function () {
    $this->get('/admin/vehicles')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['user_type' => 'EMPLOYEE']))->get('/admin/drivers')->assertForbidden();
});

test('administrators can access every configuration page', function () {
    $admin = configurationAdmin();

    foreach (['vehicles', 'drivers', 'routes', 'schedules'] as $module) {
        $this->actingAs($admin)->get('/admin/'.$module)->assertOk();
    }
});

test('administrators can manage vehicles and cannot delete referenced vehicles', function () {
    $admin = configurationAdmin();
    $response = $this->actingAs($admin)->post('/admin/vehicles', ['plate_number' => 'ABC-1234', 'vehicle_type' => 'Bus', 'capacity' => 40, 'status' => 'MAINTENANCE', 'notes' => 'Main fleet']);
    $response->assertSessionHasNoErrors()->assertRedirect(route('admin.vehicles.index', absolute: false));
    $vehicle = Vehicle::where('plate_number', 'ABC-1234')->firstOrFail();
    expect($vehicle->status)->toBe('ACTIVE');

    expect(Schema::hasColumn('vehicles', 'name'))->toBeFalse();
    $this->actingAs($admin)->get('/admin/vehicles')->assertInertia(fn (Assert $page) => $page
        ->has('vehicles', 1, fn (Assert $vehicle) => $vehicle
            ->where('plate_number', 'ABC-1234')
            ->missing('name')
            ->missing('code')
            ->etc()
        )
    );

    $this->actingAs($admin)->put('/admin/vehicles/'.$vehicle->id, ['plate_number' => 'ABC-1234', 'vehicle_type' => 'Bus', 'capacity' => 42, 'status' => 'MAINTENANCE', 'notes' => 'Updated'])->assertSessionHasNoErrors();
    expect($vehicle->fresh()->capacity)->toBe(42)->and($vehicle->fresh()->status)->toBe('MAINTENANCE');

    $route = ShuttleRoute::factory()->create();
    $driver = Driver::factory()->create();
    ShuttleSchedule::factory()->create(['vehicle_id' => $vehicle->id, 'route_id' => $route->id, 'driver_id' => $driver->id]);
    $this->actingAs($admin)->delete('/admin/vehicles/'.$vehicle->id)->assertSessionHasErrors('vehicle');
    expect($vehicle->fresh())->not->toBeNull();
});

test('driver license expiry is validated and drivers are fully manageable', function () {
    $admin = configurationAdmin();
    $invalid = $this->actingAs($admin)->post('/admin/drivers', ['name' => 'Expired Driver', 'employee_id' => 'EMP-1', 'contact_number' => '09000000000', 'license_number' => 'LIC-1', 'license_expires_at' => now()->subDay()->toDateString()]);
    $invalid->assertSessionHasErrors('license_expires_at');

    $this->actingAs($admin)->post('/admin/drivers', ['name' => 'Valid Driver', 'employee_id' => 'EMP-2', 'contact_number' => '09000000000', 'license_number' => 'LIC-2', 'license_expires_at' => now()->addYear()->toDateString(), 'status' => 'INACTIVE'])->assertSessionHasNoErrors();
    $driver = Driver::where('employee_id', 'EMP-2')->firstOrFail();
    expect($driver->status)->toBe('ACTIVE');
    $this->actingAs($admin)->put('/admin/drivers/'.$driver->id, ['name' => 'Updated Driver', 'employee_id' => 'EMP-2', 'contact_number' => '09111111111', 'license_number' => 'LIC-2', 'license_expires_at' => now()->addYear()->toDateString(), 'status' => 'INACTIVE'])->assertSessionHasNoErrors();
    expect($driver->fresh()->status)->toBe('INACTIVE');
    $this->actingAs($admin)->delete('/admin/drivers/'.$driver->id)->assertSessionHasNoErrors();
    expect($driver->fresh())->toBeNull();
});

test('routes can be managed and referenced routes cannot be deleted', function () {
    $admin = configurationAdmin();
    $this->actingAs($admin)->post('/admin/routes', ['name' => 'Main Route', 'origin' => 'PAGCOR', 'destination' => 'Makati', 'status' => 'INACTIVE'])->assertSessionHasNoErrors();
    $route = ShuttleRoute::where('name', 'Main Route')->firstOrFail();
    expect($route->status)->toBe('ACTIVE');
    $this->actingAs($admin)->put('/admin/routes/'.$route->id, ['name' => 'Updated Route', 'origin' => 'PAGCOR', 'destination' => 'Makati', 'status' => 'INACTIVE'])->assertSessionHasNoErrors();
    expect($route->fresh()->status)->toBe('INACTIVE');
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create();
    ShuttleSchedule::factory()->create(['route_id' => $route->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id]);
    $this->actingAs($admin)->delete('/admin/routes/'.$route->id)->assertSessionHasErrors('route');
});

test('schedules enforce active relationships capacity and duplicate combinations', function () {
    $admin = configurationAdmin();
    $vehicle = Vehicle::factory()->create(['capacity' => 30]);
    $driver = Driver::factory()->create();
    $route = ShuttleRoute::factory()->create();
    $payload = schedulePayload($vehicle, $driver, $route);

    $this->actingAs($admin)->post('/admin/schedules', [...$payload, 'capacity_override' => 31])->assertSessionHasErrors('capacity_override');
    $this->actingAs($admin)->post('/admin/schedules', $payload)->assertSessionHasNoErrors();
    $schedule = ShuttleSchedule::firstOrFail();
    expect($schedule->driver_id)->toBe($driver->id)->and($schedule->route_id)->toBe($route->id)->and($schedule->vehicle_id)->toBe($vehicle->id)->and($schedule->status)->toBe('ACTIVE');
    $this->actingAs($admin)->get('/admin/schedules')->assertInertia(fn (Assert $page) => $page
        ->has('schedules', 1, fn (Assert $schedule) => $schedule
            ->has('vehicle', fn (Assert $vehicleProps) => $vehicleProps
                ->where('plate_number', $vehicle->plate_number)
                ->missing('name')
                ->missing('code')
                ->etc()
            )
            ->etc()
        )
    );
    $this->actingAs($admin)->post('/admin/schedules', $payload)->assertSessionHasErrors('departure_time');

    $inactiveVehicle = Vehicle::factory()->create(['status' => 'MAINTENANCE']);
    $this->actingAs($admin)->post('/admin/schedules', [...$payload, 'vehicle_id' => $inactiveVehicle->id, 'departure_time' => '08:00', 'status' => 'INACTIVE'])->assertSessionHasErrors('vehicle_id');
    $this->actingAs($admin)->delete('/admin/schedules/'.$schedule->id)->assertSessionHasNoErrors();
    expect($schedule->fresh())->toBeNull();
});
