<?php

use App\Enums\EmployeeLoginMethod;
use App\Enums\ServiceAttendanceStatus;
use App\Enums\ServiceOccurrenceStatus;
use App\Enums\ShuttleActivityEventType;
use App\Models\Employee;
use App\Models\ShuttleSchedule;
use App\Services\ShuttleSeatPolicy;
use Carbon\CarbonImmutable;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\DriverSeeder;
use Database\Seeders\EmployeeLoginLogSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\ShuttleOperationsSeeder;
use Database\Seeders\ShuttleRouteSeeder;
use Database\Seeders\ShuttleScheduleSeeder;
use Database\Seeders\ShuttleSettingSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\VehicleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The seeders are the fixture the whole application is demoed against, so this
 * suite checks that what they write is data the application itself could have
 * produced. A shorter window keeps the run fast without changing the shapes.
 */
beforeEach(function () {
    $this->seed([
        UserSeeder::class,
        ShuttleSettingSeeder::class,
        DepartmentSeeder::class,
        EmployeeSeeder::class,
        DriverSeeder::class,
        VehicleSeeder::class,
        ShuttleRouteSeeder::class,
        ShuttleScheduleSeeder::class,
    ]);

    app(ShuttleOperationsSeeder::class)->run(historyDays: 21, futureDays: 5);
    app(EmployeeLoginLogSeeder::class)->run(historyDays: 21);
});

test('reference data covers every status the admin screens can filter on', function () {
    expect(DB::table('users')->where('user_type', 'ADMIN')->count())->toBeGreaterThanOrEqual(3)
        ->and(DB::table('users')->where('user_type', 'EMPLOYEE')->count())->toBeGreaterThanOrEqual(1);

    expect(DB::table('employees')->where('status', Employee::STATUS_ACTIVE)->count())->toBeGreaterThan(100)
        ->and(DB::table('employees')->where('status', Employee::STATUS_INACTIVE)->count())->toBeGreaterThan(0)
        ->and(DB::table('employees')->where('priority_status', Employee::PRIORITY_STATUS_PRIORITY)->count())->toBeGreaterThan(0)
        ->and(DB::table('employees')->whereNull('employee_code')->count())->toBe(0);

    foreach (DepartmentSeeder::UNSTAFFED_DEPARTMENTS as $department) {
        expect(DB::table('employees')->where('department', $department)->count())->toBe(0);
    }

    expect(DB::table('vehicles')->pluck('status')->unique()->sort()->values()->all())
        ->toBe(['ACTIVE', 'INACTIVE', 'MAINTENANCE']);
    expect(DB::table('drivers')->pluck('status')->unique()->sort()->values()->all())
        ->toBe(['ACTIVE', 'INACTIVE']);
    expect(DB::table('shuttle_routes')->pluck('status')->unique()->sort()->values()->all())
        ->toBe(['ACTIVE', 'INACTIVE']);
    expect(DB::table('shuttle_schedules')->pluck('status')->unique()->sort()->values()->all())
        ->toBe(['ACTIVE', 'INACTIVE']);
});

test('the schedule roster covers the awkward configurations', function () {
    $schedules = ShuttleSchedule::query()->get();
    $today = CarbonImmutable::now(config('shuttle.operating_timezone'))->startOfDay();

    expect($schedules->contains(fn (ShuttleSchedule $schedule): bool => $schedule->capacity_override !== null))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => $schedule->unavailable_seats !== []))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => $schedule->priority_seats === null))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => $schedule->priority_seats === []))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => ! $schedule->waitlist_enabled))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => $schedule->waitlist_capacity !== null))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => $schedule->effective_until?->lt($today) === true))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => $schedule->effective_from->gt($today)))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => count($schedule->operating_days) === 7))->toBeTrue()
        ->and($schedules->contains(fn (ShuttleSchedule $schedule): bool => in_array('saturday', $schedule->operating_days, true)))->toBeTrue();
});

test('an employee never holds more than one booking on a travel date', function () {
    $duplicateReservations = DB::table('shuttle_reservations')
        ->selectRaw('employee_id, travel_date, COUNT(*) as total')
        ->groupBy('employee_id', 'travel_date')
        ->havingRaw('COUNT(*) > 1')
        ->count();
    $duplicateSeats = DB::table('shuttle_reservations')
        ->selectRaw('shuttle_schedule_id, travel_date, seat_number, COUNT(*) as total')
        ->groupBy('shuttle_schedule_id', 'travel_date', 'seat_number')
        ->havingRaw('COUNT(*) > 1')
        ->count();
    $bookedAndQueued = DB::table('shuttle_reservations as r')
        ->join('shuttle_waitlist_entries as w', function ($join): void {
            $join->on('w.employee_id', '=', 'r.employee_id')
                ->on('w.travel_date', '=', 'r.travel_date');
        })
        ->count();

    expect($duplicateReservations)->toBe(0)
        ->and($duplicateSeats)->toBe(0)
        ->and($bookedAndQueued)->toBe(0);
});

test('every reserved seat is one the employee was allowed to take', function () {
    $seatPolicy = app(ShuttleSeatPolicy::class);
    $schedules = ShuttleSchedule::query()->with('vehicle')->get()->keyBy('id');
    $priorityEmployeeIds = Employee::query()
        ->where('priority_status', Employee::PRIORITY_STATUS_PRIORITY)
        ->pluck('employee_id')
        ->all();
    $violations = [];

    foreach (DB::table('shuttle_reservations')->get() as $reservation) {
        $schedule = $schedules->get($reservation->shuttle_schedule_id);
        $seat = (int) $reservation->seat_number;
        $isPriorityEmployee = in_array((int) $reservation->employee_id, $priorityEmployeeIds, true);

        if (! in_array($seat, $seatPolicy->eligibleSeats($schedule, $isPriorityEmployee), true)) {
            $violations[] = $reservation->id;
        }
    }

    expect($violations)->toBe([]);
});

test('service runs only exist for dates a schedule actually operates on', function () {
    $schedules = ShuttleSchedule::query()->get()->keyBy('id');
    $today = CarbonImmutable::now(config('shuttle.operating_timezone'))->startOfDay();
    $violations = [];

    foreach (DB::table('shuttle_service_occurrences')->get() as $occurrence) {
        $schedule = $schedules->get($occurrence->shuttle_schedule_id);
        $date = CarbonImmutable::parse($occurrence->travel_date);
        $operatesOnDay = in_array(
            mb_strtolower($date->format('l')),
            $schedule->operating_days ?? [],
            true
        );
        $withinRange = $schedule->effective_from->toDateString() <= $date->toDateString()
            && (
                $schedule->effective_until === null
                || $schedule->effective_until->toDateString() >= $date->toDateString()
            );

        if (! $operatesOnDay || ! $withinRange || $date->gt($today) || $schedule->status !== 'ACTIVE') {
            $violations[] = $occurrence->id;
        }
    }

    expect($violations)->toBe([])
        ->and(DB::table('shuttle_service_occurrences')->count())->toBeGreaterThan(0);
});

test('every service and attendance outcome is represented', function () {
    $occurrenceStatuses = DB::table('shuttle_service_occurrences')
        ->pluck('status')
        ->unique();

    foreach (ServiceOccurrenceStatus::cases() as $status) {
        expect($occurrenceStatuses)->toContain($status->value);
    }

    $attendanceStatuses = DB::table('shuttle_service_attendances')->pluck('status')->unique();

    foreach (ServiceAttendanceStatus::cases() as $status) {
        expect($attendanceStatuses)->toContain($status->value);
    }

    $recordingMethods = DB::table('shuttle_service_attendances')
        ->pluck('recording_method')
        ->unique();

    expect($recordingMethods)->toContain('QR_SCAN', 'MANUAL', 'FINALIZATION');

    $eventTypes = DB::table('shuttle_activity_events')->pluck('event_type')->unique();

    foreach (ShuttleActivityEventType::cases() as $eventType) {
        expect($eventTypes)->toContain($eventType->value);
    }

    $loginMethods = DB::table('employee_login_logs')->pluck('login_method')->unique();

    foreach (EmployeeLoginMethod::cases() as $method) {
        expect($loginMethods)->toContain($method->value);
    }

    expect(DB::table('shuttle_service_corrections')->pluck('action')->unique())
        ->toContain('CORRECTION', 'REOPEN');
});

test('finalized runs are closed out consistently', function () {
    $finalized = DB::table('shuttle_service_occurrences')
        ->whereIn('status', [
            ServiceOccurrenceStatus::Completed->value,
            ServiceOccurrenceStatus::NotOperated->value,
        ])
        ->get();
    $problems = [];

    foreach ($finalized as $occurrence) {
        $openWaitlist = DB::table('shuttle_waitlist_entries')
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->where('travel_date', $occurrence->travel_date)
            ->count();
        $reservations = DB::table('shuttle_reservations')
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->where('travel_date', $occurrence->travel_date)
            ->count();
        $attendances = DB::table('shuttle_service_attendances')
            ->where('shuttle_service_occurrence_id', $occurrence->id)
            ->count();

        if (
            $openWaitlist > 0
            || $occurrence->finalized_by === null
            || (int) $occurrence->reservation_count !== $reservations
            || $attendances !== $reservations
        ) {
            $problems[] = $occurrence->id;
        }
    }

    expect($problems)->toBe([]);

    $completed = DB::table('shuttle_service_occurrences')
        ->where('status', ServiceOccurrenceStatus::Completed->value)
        ->get();

    foreach ($completed as $occurrence) {
        expect($occurrence->opening_odometer_km)->not->toBeNull()
            ->and((float) $occurrence->closing_odometer_km)
            ->toBeGreaterThanOrEqual((float) $occurrence->opening_odometer_km);
    }

    expect(
        DB::table('shuttle_service_occurrences')
            ->where('status', ServiceOccurrenceStatus::NotOperated->value)
            ->whereNull('not_operated_reason')
            ->count()
    )->toBe(0);
});

test('odometer readings per vehicle never move backwards', function () {
    $readings = DB::table('shuttle_service_occurrences')
        ->where('status', ServiceOccurrenceStatus::Completed->value)
        ->orderBy('vehicle_id')
        ->orderBy('scheduled_departure_at')
        ->get(['vehicle_id', 'opening_odometer_km', 'closing_odometer_km']);
    $previousClosingByVehicle = [];
    $violations = 0;

    foreach ($readings as $reading) {
        $previousClosing = $previousClosingByVehicle[$reading->vehicle_id] ?? null;

        if ($previousClosing !== null && (float) $reading->opening_odometer_km < $previousClosing) {
            $violations++;
        }

        $previousClosingByVehicle[$reading->vehicle_id] = (float) $reading->closing_odometer_km;
    }

    expect($violations)->toBe(0);
});

test('open runs carry counts that match their reservations and attendance', function () {
    $open = DB::table('shuttle_service_occurrences')
        ->whereIn('status', [
            ServiceOccurrenceStatus::Scheduled->value,
            ServiceOccurrenceStatus::AwaitingCompletion->value,
        ])
        ->get();
    $problems = [];

    foreach ($open as $occurrence) {
        $reservations = DB::table('shuttle_reservations')
            ->where('shuttle_schedule_id', $occurrence->shuttle_schedule_id)
            ->where('travel_date', $occurrence->travel_date)
            ->count();
        $boarded = DB::table('shuttle_service_attendances')
            ->where('shuttle_service_occurrence_id', $occurrence->id)
            ->where('status', ServiceAttendanceStatus::Boarded->value)
            ->count();

        if (
            (int) $occurrence->reservation_count !== $reservations
            || (int) $occurrence->boarded_count !== $boarded
            || (int) $occurrence->no_show_count !== 0
            || $occurrence->finalized_at !== null
        ) {
            $problems[] = $occurrence->id;
        }
    }

    expect($problems)->toBe([]);
});

test('the scripted personas land in the states the demo script promises', function () {
    $today = CarbonImmutable::now(config('shuttle.operating_timezone'))->startOfDay()->toDateString();
    $tomorrow = CarbonImmutable::parse($today)->addDay()->toDateString();

    $personaId = fn (string $email): int => (int) Employee::query()
        ->where('email', $email)
        ->value('employee_id');

    expect(
        DB::table('shuttle_reservations')
            ->where('employee_id', $personaId(EmployeeSeeder::PERSONA_TODAY_UPCOMING))
            ->where('travel_date', $today)
            ->exists()
    )->toBeTrue();

    expect(
        DB::table('shuttle_service_attendances')
            ->where('employee_id', $personaId(EmployeeSeeder::PERSONA_TODAY_BOARDED))
            ->where('status', ServiceAttendanceStatus::Boarded->value)
            ->exists()
    )->toBeTrue();

    expect(
        DB::table('shuttle_waitlist_entries')
            ->where('employee_id', $personaId(EmployeeSeeder::PERSONA_TODAY_WAITLIST))
            ->where('travel_date', $today)
            ->exists()
    )->toBeTrue();

    expect(
        DB::table('shuttle_reservations')
            ->where('employee_id', $personaId(EmployeeSeeder::PERSONA_FUTURE_ONLY))
            ->where('travel_date', $tomorrow)
            ->exists()
    )->toBeTrue();

    expect(
        DB::table('shuttle_reservations')
            ->where('employee_id', $personaId(EmployeeSeeder::PERSONA_NEVER_BOOKS))
            ->exists()
    )->toBeFalse();

    expect(
        DB::table('shuttle_reservations')
            ->where('employee_id', $personaId(EmployeeSeeder::PERSONA_HEAVY_HISTORY))
            ->count()
    )->toBeGreaterThan(1);
});

test('bookings exist ahead of today but service runs do not', function () {
    $today = CarbonImmutable::now(config('shuttle.operating_timezone'))->startOfDay()->toDateString();

    expect(DB::table('shuttle_reservations')->where('travel_date', '>', $today)->count())
        ->toBeGreaterThan(0)
        ->and(DB::table('shuttle_service_occurrences')->where('travel_date', '>', $today)->count())
        ->toBe(0)
        ->and(DB::table('employee_login_logs')->count())
        ->toBeGreaterThan(0);
});
