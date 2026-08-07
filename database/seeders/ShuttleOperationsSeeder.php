<?php

namespace Database\Seeders;

use App\Enums\AttendanceRecordingMethod;
use App\Enums\ServiceAttendanceStatus;
use App\Enums\ServiceOccurrenceStatus;
use App\Enums\ShuttleActivityEventType;
use App\Models\Employee;
use App\Models\ShuttleReservation;
use App\Models\ShuttleSchedule;
use App\Models\User;
use App\Services\ShuttleSeatPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates the day-to-day life of the shuttle service: reservations, waitlists,
 * materialized service runs, attendance, closeouts and the audit trail that goes
 * with them.
 *
 * The generator mirrors the rules the application enforces at runtime, so the
 * seeded data is data the app itself could have produced:
 *
 * - an employee holds at most one reservation or waitlist entry per travel date;
 * - regular employees never occupy a priority-only seat, and nobody occupies a
 *   seat that is blocked or beyond the effective capacity;
 * - service runs only exist for dates a schedule actually operates on, and only
 *   up to the current day, because the materializer runs one day at a time;
 * - odometer readings per vehicle never move backwards;
 * - finalizing a service clears its waitlist into "unserved" activity events.
 *
 * Everything it writes is demo data, so it clears the operational tables first.
 */
class ShuttleOperationsSeeder extends Seeder
{
    private const RANDOM_SEED = 20260807;

    private const INSERT_CHUNK = 200;

    /** Employees deactivated long ago still appear in older history. */
    private const INACTIVE_HISTORY_CUTOFF_DAYS = 15;

    /** Approximate one-way distance from headquarters, keyed by destination. */
    private const ROUTE_DISTANCES_KM = [
        'Manila' => 6.0,
        'Quezon City' => 14.0,
        'Makati City' => 9.0,
        'Pasay City' => 5.0,
        'Parañaque City' => 12.0,
        'Taguig City' => 13.0,
        'Mandaluyong City' => 9.0,
        'Pasig City' => 14.0,
        'Caloocan City' => 16.0,
        'Marikina City' => 20.0,
        'Muntinlupa City' => 24.0,
        'Valenzuela City' => 20.0,
        'Las Piñas City' => 18.0,
        'Malabon City' => 17.0,
        'Navotas City' => 15.0,
        'San Juan City' => 11.0,
        'Antipolo City' => 28.0,
        'Bacoor City' => 22.0,
        'Imus City' => 27.0,
        'Dasmariñas City' => 33.0,
        'Biñan City' => 38.0,
        'Santa Rosa City' => 43.0,
        'San Pedro City' => 30.0,
        'Calamba City' => 52.0,
    ];

    /** @var array<int, array{code: ?string, name: string, department: ?string, priority: string}> */
    private array $employees = [];

    /** @var list<int> */
    private array $activeEmployeeIds = [];

    /** @var list<int> */
    private array $inactiveEmployeeIds = [];

    /** @var array<string, int> */
    private array $personaIds = [];

    /** @var list<array{id: int, name: string}> */
    private array $administrators = [];

    /** @var Collection<int, ShuttleSchedule> */
    private Collection $schedules;

    /** Persona ids already pinned to a seat today, so they are never overwritten. */
    private array $placedPersonaIds = [];

    /** @var array<int, float> */
    private array $scheduleDemand = [];

    /** @var array<int, float> */
    private array $vehicleOdometer = [];

    public function __construct(private ShuttleSeatPolicy $seatPolicy) {}

    /**
     * Run the database seeds.
     *
     * @param  int  $historyDays  How far back the generated history reaches.
     * @param  int  $futureDays  How far ahead employees have already booked.
     */
    public function run(int $historyDays = 60, int $futureDays = 14): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'ShuttleOperationsSeeder writes demo data and clears operational tables; it must not run in production.'
            );
        }

        mt_srand(self::RANDOM_SEED);

        $timezone = (string) config('shuttle.operating_timezone', 'Asia/Manila');
        $now = CarbonImmutable::now($timezone);
        $today = $now->startOfDay();

        $this->purgeOperationalData();
        $this->loadReferenceData();

        if (
            $this->schedules->isEmpty()
            || $this->activeEmployeeIds === []
            || $this->administrators === []
        ) {
            $this->command?->warn(
                'Schedules, employees, or administrators are missing; skipping shuttle operations.'
            );

            return;
        }

        for ($offset = -$historyDays; $offset <= $futureDays; $offset++) {
            $this->seedDay($today->addDays($offset), $now, $today);
        }

        $this->seedCorrections($now);
    }

    /**
     * Demo runs are regenerated from scratch. Deleting in dependency order keeps
     * foreign keys satisfied, and going through the query builder sidesteps the
     * model guards that make audit records immutable.
     */
    private function purgeOperationalData(): void
    {
        foreach ([
            'shuttle_service_corrections',
            'shuttle_service_attendances',
            'shuttle_activity_events',
            'shuttle_waitlist_entries',
            'shuttle_reservations',
            'shuttle_service_occurrences',
            'employee_login_logs',
        ] as $table) {
            DB::table($table)->delete();
        }
    }

    private function loadReferenceData(): void
    {
        Employee::query()
            ->orderBy('employee_id')
            ->get(['employee_id', 'employee_code', 'name', 'department', 'priority_status', 'status', 'email'])
            ->each(function (Employee $employee): void {
                $id = (int) $employee->getKey();
                $this->employees[$id] = [
                    'code' => $employee->employee_code,
                    'name' => $employee->name,
                    'department' => $employee->department,
                    'priority' => $employee->priority_status,
                ];
                $this->personaIds[(string) $employee->email] = $id;

                if ($employee->isActive()) {
                    $this->activeEmployeeIds[] = $id;

                    return;
                }

                $this->inactiveEmployeeIds[] = $id;
            });

        $this->administrators = User::query()
            ->whereIn('email', UserSeeder::closeoutAdministratorEmails())
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => (int) $user->getKey(), 'name' => $user->name])
            ->all();

        if ($this->administrators === []) {
            $this->administrators = User::query()
                ->where('user_type', 'ADMIN')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['id' => (int) $user->getKey(), 'name' => $user->name])
                ->all();
        }

        $this->schedules = ShuttleSchedule::query()
            ->with([
                'route:id,name,origin,destination,status',
                'vehicle:id,plate_number,vehicle_type,capacity,status',
                'driver:id,name,employee_id,status',
            ])
            ->where('status', 'ACTIVE')
            ->orderBy('departure_time')
            ->orderBy('id')
            ->get();

        foreach ($this->schedules as $schedule) {
            /* A fixed popularity per schedule keeps busy routes busy every day. */
            $this->scheduleDemand[(int) $schedule->getKey()] = $this->randomFloat(0.3, 1.12);
        }

        foreach ($this->schedules->pluck('vehicle_id')->unique() as $vehicleId) {
            $this->vehicleOdometer[(int) $vehicleId] = (float) mt_rand(45000, 210000);
        }
    }

    private function seedDay(
        CarbonImmutable $date,
        CarbonImmutable $now,
        CarbonImmutable $today,
    ): void {
        $schedules = $this->schedulesOperatingOn($date);

        if ($schedules === []) {
            return;
        }

        $pool = $this->dailyPool($date, $today);
        $targets = $this->passengerTargets($schedules, $date, $today, $pool);
        $plans = [];

        foreach ($schedules as $index => $schedule) {
            $plans[] = $this->planService($schedule, $date, $now, $today, $targets[$index], $pool);
        }

        if ($date->equalTo($today)) {
            $this->applyCurrentDayPersonas($plans, $pool, $now);
        }

        if ($date->equalTo($today->addDay())) {
            $this->applyNextDayPersonas($plans, $now);
        }

        $this->persistDay($date, $plans, $date->lte($today));
    }

    /**
     * @return list<ShuttleSchedule>
     */
    private function schedulesOperatingOn(CarbonImmutable $date): array
    {
        $operatingDay = mb_strtolower($date->format('l'));
        $travelDate = $date->toDateString();

        return $this->schedules
            ->filter(function (ShuttleSchedule $schedule) use ($operatingDay, $travelDate): bool {
                if (! in_array($operatingDay, $schedule->operating_days ?? [], true)) {
                    return false;
                }

                if ($schedule->effective_from->toDateString() > $travelDate) {
                    return false;
                }

                return $schedule->effective_until === null
                    || $schedule->effective_until->toDateString() >= $travelDate;
            })
            ->sortBy(fn (ShuttleSchedule $schedule): string => (string) $schedule->departure_time)
            ->values()
            ->all();
    }

    /**
     * The pool of employees available to book on a date. Because the application
     * allows only one booking per employee per day, employees are drawn from here
     * and never put back.
     *
     * @return array{priority: list<int>, regular: list<int>}
     */
    private function dailyPool(CarbonImmutable $date, CarbonImmutable $today): array
    {
        $candidateIds = $this->activeEmployeeIds;

        if ($date->lt($today->subDays(self::INACTIVE_HISTORY_CUTOFF_DAYS))) {
            $candidateIds = [...$candidateIds, ...$this->inactiveEmployeeIds];
        }

        $excluded = $this->excludedEmployeeIds($date, $today);
        $priority = [];
        $regular = [];

        foreach ($candidateIds as $employeeId) {
            if (in_array($employeeId, $excluded, true)) {
                continue;
            }

            if ($this->employees[$employeeId]['priority'] === Employee::PRIORITY_STATUS_PRIORITY) {
                $priority[] = $employeeId;

                continue;
            }

            $regular[] = $employeeId;
        }

        shuffle($priority);
        shuffle($regular);

        return ['priority' => $priority, 'regular' => $regular];
    }

    /**
     * @return list<int>
     */
    private function excludedEmployeeIds(CarbonImmutable $date, CarbonImmutable $today): array
    {
        $excluded = [$this->personaId(EmployeeSeeder::PERSONA_NEVER_BOOKS)];

        if ($date->gte($today)) {
            /* Scripted personas are placed by hand for today and tomorrow. */
            foreach (EmployeeSeeder::scriptedPersonaEmails() as $email) {
                $excluded[] = $this->personaId($email);
            }
        }

        if ($date->lt($today)) {
            $excluded[] = $this->personaId(EmployeeSeeder::PERSONA_FUTURE_ONLY);
        }

        return array_values(array_filter($excluded));
    }

    private function personaId(string $email): ?int
    {
        return $this->personaIds[$email] ?? null;
    }

    /**
     * Builds one service run: who is on board, who is queued, what happened to
     * the run, and the activity trail behind it.
     *
     * @param  array{priority: list<int>, regular: list<int>}  $pool
     * @return array<string, mixed>
     */
    private function planService(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        CarbonImmutable $now,
        CarbonImmutable $today,
        int $target,
        array &$pool,
    ): array {
        $departureAt = CarbonImmutable::parse(
            $date->toDateString().' '.(string) $schedule->departure_time,
            $now->getTimezone()
        );
        $availableSeats = $this->seatPolicy->availableSeats($schedule);
        $prioritySeats = array_values(array_intersect(
            $this->seatPolicy->effectivePrioritySeats($schedule),
            $availableSeats
        ));
        $regularSeats = array_values(array_diff($availableSeats, $prioritySeats));
        $status = $this->serviceStatus($date, $today, $departureAt, $now);
        $target = min($target, count($availableSeats));

        $plan = [
            'schedule' => $schedule,
            'departure_at' => $departureAt,
            'status' => $status,
            'effective_capacity' => $this->seatPolicy->effectiveCapacity($schedule),
            'available_seats' => $availableSeats,
            'priority_seats' => $prioritySeats,
            'regular_seats' => $regularSeats,
            'passengers' => [],
            'waitlist' => [],
            'unserved_waitlist' => [],
            'events' => [],
        ];

        $seatsToFill = $this->chooseSeats($prioritySeats, $regularSeats, $target, $pool);

        foreach ($seatsToFill as $seat => $employeeId) {
            $passenger = $this->planPassenger(
                $schedule,
                $date,
                $departureAt,
                $now,
                $employeeId,
                (int) $seat,
                ShuttleReservation::SOURCE_SELECTED,
                $plan['status'],
                $plan
            );
            $plan['passengers'][] = $passenger;
        }

        $this->planWaitlist($plan, $schedule, $date, $departureAt, $now, $pool);
        $this->planCancellation($plan, $schedule, $date, $departureAt, $now);
        $this->planSeatChange($plan, $schedule, $date, $departureAt, $now);

        return $plan;
    }

    private function serviceStatus(
        CarbonImmutable $date,
        CarbonImmutable $today,
        CarbonImmutable $departureAt,
        CarbonImmutable $now,
    ): ?ServiceOccurrenceStatus {
        if ($date->gt($today)) {
            /* The materializer only ever creates the current day's runs. */
            return null;
        }

        if ($date->equalTo($today)) {
            if ($departureAt->greaterThan($now)) {
                return ServiceOccurrenceStatus::Scheduled;
            }

            /* Departed runs are mostly still open, so the closeout queue is never
             * empty when the seeded database is opened. */
            return ServiceOccurrenceStatus::from($this->weighted([
                ServiceOccurrenceStatus::AwaitingCompletion->value => 65,
                ServiceOccurrenceStatus::Completed->value => 25,
                ServiceOccurrenceStatus::NotOperated->value => 10,
            ]));
        }

        /* A small backlog of recent runs is left open for the closeout screens. */
        if ($date->gte($today->subDays(3)) && mt_rand(1, 100) <= 14) {
            return ServiceOccurrenceStatus::AwaitingCompletion;
        }

        return mt_rand(1, 100) <= 7
            ? ServiceOccurrenceStatus::NotOperated
            : ServiceOccurrenceStatus::Completed;
    }

    /**
     * How many people ride each service that day.
     *
     * Because an employee may only book once a day, the whole roster is competing
     * for one pool of people. Raw demand is therefore scaled to fit the pool, or
     * the early departures would swallow everyone and the evening runs would
     * leave empty. A handful of services are pinned to a full load first so there
     * is always somewhere for a waitlist to form.
     *
     * @param  list<ShuttleSchedule>  $schedules
     * @param  array{priority: list<int>, regular: list<int>}  $pool
     * @return list<int>
     */
    private function passengerTargets(
        array $schedules,
        CarbonImmutable $date,
        CarbonImmutable $today,
        array $pool,
    ): array {
        $seatCounts = [];
        $rawTargets = [];
        $pinnedFull = [];

        foreach ($schedules as $index => $schedule) {
            $seatCount = count($this->seatPolicy->availableSeats($schedule));
            $demand = $this->scheduleDemand[(int) $schedule->getKey()]
                * $this->randomFloat(0.65, 1.35)
                * $this->dateDemandFactor($date, $today);

            $seatCounts[$index] = $seatCount;
            $rawTargets[$index] = max(0, min($seatCount, (int) round($seatCount * $demand)));
            $pinnedFull[$index] = $seatCount > 0
                && $seatCount <= 35
                && $date->lte($today)
                && mt_rand(1, 100) <= 14;
        }

        /* Leave headroom so waitlists and the tail of the day still find people. */
        $budget = (int) floor((count($pool['priority']) + count($pool['regular'])) * 0.82);
        $pinnedTotal = 0;
        $flexibleTotal = 0;

        foreach ($rawTargets as $index => $target) {
            if ($pinnedFull[$index]) {
                $pinnedTotal += $seatCounts[$index];

                continue;
            }

            $flexibleTotal += $target;
        }

        $flexibleBudget = max(0, $budget - $pinnedTotal);
        $scale = $flexibleTotal > $flexibleBudget && $flexibleTotal > 0
            ? $flexibleBudget / $flexibleTotal
            : 1.0;
        $targets = [];

        foreach ($rawTargets as $index => $target) {
            $targets[$index] = $pinnedFull[$index]
                ? $seatCounts[$index]
                : (int) round($target * $scale);
        }

        return $targets;
    }

    /**
     * Bookings thin out the further ahead the travel date is, and the weekend
     * skeleton services carry far fewer people.
     */
    private function dateDemandFactor(CarbonImmutable $date, CarbonImmutable $today): float
    {
        $factor = $date->isWeekend() ? 0.45 : 1.0;

        if ($date->lte($today)) {
            return $factor;
        }

        $daysAhead = $today->diffInDays($date);

        return $factor * max(0.05, 1.0 - ($daysAhead * 0.09));
    }

    /**
     * Assigns employees to seats. Priority-only seats are filled from the
     * priority tier and are often left partly empty, exactly as they are in
     * practice; every other seat is filled from whoever is left.
     *
     * @param  list<int>  $prioritySeats
     * @param  list<int>  $regularSeats
     * @param  array{priority: list<int>, regular: list<int>}  $pool
     * @return array<int, int> Seat number keyed to employee id.
     */
    private function chooseSeats(
        array $prioritySeats,
        array $regularSeats,
        int $target,
        array &$pool,
    ): array {
        $assignments = [];
        shuffle($prioritySeats);
        shuffle($regularSeats);

        foreach ($prioritySeats as $seat) {
            if (count($assignments) >= $target || $pool['priority'] === []) {
                break;
            }

            if (mt_rand(1, 100) > 55) {
                continue;
            }

            $assignments[$seat] = array_pop($pool['priority']);
        }

        foreach ($regularSeats as $seat) {
            if (count($assignments) >= $target) {
                break;
            }

            $employeeId = $this->drawAnyEmployee($pool);

            if ($employeeId === null) {
                break;
            }

            $assignments[$seat] = $employeeId;
        }

        ksort($assignments);

        return $assignments;
    }

    /**
     * @param  array{priority: list<int>, regular: list<int>}  $pool
     */
    private function drawAnyEmployee(array &$pool): ?int
    {
        /* Priority employees also ride in ordinary seats, just less often. */
        if ($pool['priority'] !== [] && mt_rand(1, 100) <= 12) {
            return array_pop($pool['priority']);
        }

        if ($pool['regular'] !== []) {
            return array_pop($pool['regular']);
        }

        return $pool['priority'] === [] ? null : array_pop($pool['priority']);
    }

    /**
     * @param  array{priority: list<int>, regular: list<int>}  $pool
     */
    private function drawRegularEmployee(array &$pool): ?int
    {
        return $pool['regular'] === [] ? null : array_pop($pool['regular']);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function planPassenger(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        CarbonImmutable $departureAt,
        CarbonImmutable $now,
        int $employeeId,
        int $seat,
        string $source,
        ?ServiceOccurrenceStatus $status,
        array &$plan,
    ): array {
        $reservedAt = $this->bookingMoment($departureAt, $now);
        $plan['events'][] = $this->activityEvent(
            $schedule,
            $date,
            $employeeId,
            ShuttleActivityEventType::ReservationCreated,
            $seat,
            $reservedAt,
            ['source' => $source],
        );

        return [
            'employee_id' => $employeeId,
            'seat' => $seat,
            'source' => $source,
            'reserved_at' => $reservedAt,
            ...$this->planAttendance($employeeId, $status, $departureAt, $now),
        ];
    }

    /**
     * @return array{
     *     attendance: ?ServiceAttendanceStatus,
     *     recording_method: ?AttendanceRecordingMethod,
     *     boarded_at: ?CarbonImmutable
     * }
     */
    private function planAttendance(
        int $employeeId,
        ?ServiceOccurrenceStatus $status,
        CarbonImmutable $departureAt,
        CarbonImmutable $now,
    ): array {
        $none = ['attendance' => null, 'recording_method' => null, 'boarded_at' => null];

        if ($status === null || $status === ServiceOccurrenceStatus::Scheduled) {
            return $none;
        }

        if ($status === ServiceOccurrenceStatus::NotOperated) {
            return [
                'attendance' => ServiceAttendanceStatus::ServiceNotOperated,
                'recording_method' => AttendanceRecordingMethod::Finalization,
                'boarded_at' => null,
            ];
        }

        $boardingChance = $employeeId === $this->personaId(EmployeeSeeder::PERSONA_FREQUENT_NO_SHOW)
            ? 45
            : 86;

        if ($status === ServiceOccurrenceStatus::AwaitingCompletion) {
            /* Boarding is still in progress; no-shows only exist after closeout. */
            return mt_rand(1, 100) <= 60
                ? $this->boardedAttendance($departureAt, $now)
                : $none;
        }

        if (mt_rand(1, 100) <= $boardingChance) {
            return $this->boardedAttendance($departureAt, $now);
        }

        return [
            'attendance' => ServiceAttendanceStatus::NoShow,
            'recording_method' => AttendanceRecordingMethod::Finalization,
            'boarded_at' => null,
        ];
    }

    /**
     * @return array{
     *     attendance: ServiceAttendanceStatus,
     *     recording_method: AttendanceRecordingMethod,
     *     boarded_at: CarbonImmutable
     * }
     */
    private function boardedAttendance(
        CarbonImmutable $departureAt,
        CarbonImmutable $now,
    ): array {
        $boardedAt = $departureAt->subMinutes(mt_rand(-4, 22));

        return [
            'attendance' => ServiceAttendanceStatus::Boarded,
            'recording_method' => mt_rand(1, 100) <= 75
                ? AttendanceRecordingMethod::QrScan
                : AttendanceRecordingMethod::Manual,
            'boarded_at' => $boardedAt->greaterThan($now) ? $now : $boardedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array{priority: list<int>, regular: list<int>}  $pool
     */
    private function planWaitlist(
        array &$plan,
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        CarbonImmutable $departureAt,
        CarbonImmutable $now,
        array &$pool,
    ): void {
        if (! $schedule->waitlist_enabled) {
            return;
        }

        $occupiedSeats = array_column($plan['passengers'], 'seat');
        $regularSeatsLeft = array_diff($plan['regular_seats'], $occupiedSeats);
        $anySeatsLeft = array_diff($plan['available_seats'], $occupiedSeats);

        /* Employees may only queue once every seat they are allowed to take has
         * gone: regular employees ignore the priority block, priority employees
         * need the whole shuttle to be full. */
        if ($regularSeatsLeft !== [] || $plan['regular_seats'] === []) {
            return;
        }

        $priorityMayQueue = $anySeatsLeft === [];
        $capacity = $schedule->waitlist_capacity ?? 12;
        $queueLength = min($capacity, mt_rand(1, 8));

        for ($position = 0; $position < $queueLength; $position++) {
            $employeeId = $priorityMayQueue
                ? $this->drawAnyEmployee($pool)
                : $this->drawRegularEmployee($pool);

            if ($employeeId === null) {
                break;
            }

            $queuedAt = $this->bookingMoment($departureAt, $now);
            $plan['waitlist'][] = ['employee_id' => $employeeId, 'queued_at' => $queuedAt];
            $plan['events'][] = $this->activityEvent(
                $schedule,
                $date,
                $employeeId,
                ShuttleActivityEventType::WaitlistJoined,
                null,
                $queuedAt,
            );
        }

        /* Somebody queues then changes their mind. */
        if ($plan['waitlist'] !== [] && mt_rand(1, 100) <= 18) {
            $employeeId = $priorityMayQueue
                ? $this->drawAnyEmployee($pool)
                : $this->drawRegularEmployee($pool);

            if ($employeeId !== null) {
                $queuedAt = $this->bookingMoment($departureAt, $now);
                $plan['events'][] = $this->activityEvent(
                    $schedule,
                    $date,
                    $employeeId,
                    ShuttleActivityEventType::WaitlistJoined,
                    null,
                    $queuedAt,
                );
                $plan['events'][] = $this->activityEvent(
                    $schedule,
                    $date,
                    $employeeId,
                    ShuttleActivityEventType::WaitlistWithdrawn,
                    null,
                    $this->between($queuedAt, $departureAt),
                    ['queued_at' => $queuedAt->toIso8601String()],
                );
            }
        }
    }

    /**
     * A cancellation frees a seat. When somebody eligible is queued, the seat is
     * handed to them exactly the way the reservation service would.
     *
     * @param  array<string, mixed>  $plan
     */
    private function planCancellation(
        array &$plan,
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        CarbonImmutable $departureAt,
        CarbonImmutable $now,
    ): void {
        /* Seats on a shuttle with a queue change hands far more often, which is
         * also where a cancellation is interesting: it promotes somebody. */
        $cancellationChance = $plan['waitlist'] === [] ? 8 : 35;

        if ($plan['passengers'] === [] || mt_rand(1, 100) > $cancellationChance) {
            return;
        }

        $index = array_rand($plan['passengers']);
        $passenger = $plan['passengers'][$index];
        $cancelledAt = $this->between($passenger['reserved_at'], $departureAt);

        if ($cancelledAt->greaterThan($now)) {
            return;
        }

        unset($plan['passengers'][$index]);
        $plan['passengers'] = array_values($plan['passengers']);
        $plan['events'][] = $this->activityEvent(
            $schedule,
            $date,
            $passenger['employee_id'],
            ShuttleActivityEventType::ReservationCancelled,
            $passenger['seat'],
            $cancelledAt,
            [
                'source' => $passenger['source'],
                'reserved_at' => $passenger['reserved_at']->toIso8601String(),
                'cancelled_at' => $cancelledAt->toIso8601String(),
                'scheduled_departure_at' => $departureAt->toIso8601String(),
                'cancellation_lead_minutes' => max(
                    0,
                    (int) $cancelledAt->diffInMinutes($departureAt, absolute: false)
                ),
            ],
        );

        $promotedIndex = $this->nextPromotableWaitlistIndex($plan, $passenger['seat']);

        if ($promotedIndex === null) {
            return;
        }

        $promoted = $plan['waitlist'][$promotedIndex];
        unset($plan['waitlist'][$promotedIndex]);
        $plan['waitlist'] = array_values($plan['waitlist']);

        $plan['passengers'][] = [
            'employee_id' => $promoted['employee_id'],
            'seat' => $passenger['seat'],
            'source' => ShuttleReservation::SOURCE_AUTO_ASSIGNED,
            'reserved_at' => $cancelledAt,
            ...$this->planAttendance(
                $promoted['employee_id'],
                $plan['status'],
                $departureAt,
                $now
            ),
        ];
        $plan['events'][] = $this->activityEvent(
            $schedule,
            $date,
            $promoted['employee_id'],
            ShuttleActivityEventType::WaitlistPromoted,
            $passenger['seat'],
            $cancelledAt,
            ['queued_at' => $promoted['queued_at']->toIso8601String()],
        );
    }

    /**
     * The queue is served oldest first, but a priority-only seat can only go to a
     * priority employee.
     *
     * @param  array<string, mixed>  $plan
     */
    private function nextPromotableWaitlistIndex(array $plan, int $seat): ?int
    {
        $priorityOnly = in_array($seat, $plan['priority_seats'], true);

        foreach ($plan['waitlist'] as $index => $entry) {
            if (
                $priorityOnly
                && $this->employees[$entry['employee_id']]['priority'] !== Employee::PRIORITY_STATUS_PRIORITY
            ) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function planSeatChange(
        array &$plan,
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        CarbonImmutable $departureAt,
        CarbonImmutable $now,
    ): void {
        if ($plan['passengers'] === [] || mt_rand(1, 100) > 10) {
            return;
        }

        $index = array_rand($plan['passengers']);
        $passenger = $plan['passengers'][$index];
        $takenSeats = array_column($plan['passengers'], 'seat');
        $candidateSeats = array_values(array_diff($plan['available_seats'], $takenSeats));

        if ($this->employees[$passenger['employee_id']]['priority'] !== Employee::PRIORITY_STATUS_PRIORITY) {
            $candidateSeats = array_values(array_diff($candidateSeats, $plan['priority_seats']));
        }

        if ($candidateSeats === []) {
            return;
        }

        $changedAt = $this->between($passenger['reserved_at'], $departureAt);

        if ($changedAt->greaterThan($now)) {
            return;
        }

        $plan['events'][] = $this->activityEvent(
            $schedule,
            $date,
            $passenger['employee_id'],
            ShuttleActivityEventType::ReservationSeatChanged,
            $passenger['seat'],
            $changedAt,
            [
                'source' => $passenger['source'],
                'previous_seat_number' => $candidateSeats[array_rand($candidateSeats)],
            ],
        );
    }

    /**
     * Puts the scripted personas into the states the manual test script expects.
     *
     * @param  list<array<string, mixed>>  $plans
     */
    private function applyCurrentDayPersonas(
        array &$plans,
        array &$pool,
        CarbonImmutable $now,
    ): void {
        $this->placedPersonaIds = [];
        $this->ensureQueuedService($plans, $pool, $now);

        $this->placePersona(
            $plans,
            EmployeeSeeder::PERSONA_TODAY_UPCOMING,
            $now,
            fn (array $plan): bool => $plan['status'] === ServiceOccurrenceStatus::Scheduled,
        );
        $this->placePersona(
            $plans,
            EmployeeSeeder::PERSONA_HEAVY_HISTORY,
            $now,
            fn (array $plan): bool => $plan['status'] === ServiceOccurrenceStatus::Scheduled,
        );
        $this->placePersona(
            $plans,
            EmployeeSeeder::PERSONA_TODAY_BOARDED,
            $now,
            fn (array $plan): bool => $plan['status'] === ServiceOccurrenceStatus::AwaitingCompletion,
            forceBoarded: true,
        );
        $this->queuePersona($plans, EmployeeSeeder::PERSONA_TODAY_WAITLIST);
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function applyNextDayPersonas(array &$plans, CarbonImmutable $now): void
    {
        $this->placedPersonaIds = [];
        $this->placePersona(
            $plans,
            EmployeeSeeder::PERSONA_FUTURE_ONLY,
            $now,
            fn (array $plan): bool => true,
        );
    }

    /**
     * Guarantees the current day has at least one open service whose seats are
     * gone and whose queue is growing, whatever the random draw produced.
     *
     * @param  list<array<string, mixed>>  $plans
     * @param  array{priority: list<int>, regular: list<int>}  $pool
     */
    private function ensureQueuedService(
        array &$plans,
        array &$pool,
        CarbonImmutable $now,
    ): void {
        foreach ($plans as $plan) {
            if ($plan['waitlist'] !== [] && $plan['status']?->isFinalized() === false) {
                return;
            }
        }

        $candidateIndex = null;
        $smallestSeatCount = PHP_INT_MAX;

        foreach ($plans as $index => $plan) {
            if (
                $plan['status'] === null
                || $plan['status']->isFinalized()
                || ! $plan['schedule']->waitlist_enabled
                || $plan['regular_seats'] === []
            ) {
                continue;
            }

            if (count($plan['available_seats']) < $smallestSeatCount) {
                $smallestSeatCount = count($plan['available_seats']);
                $candidateIndex = $index;
            }
        }

        if ($candidateIndex === null) {
            return;
        }

        $plan = $plans[$candidateIndex];
        $occupied = array_column($plan['passengers'], 'seat');

        foreach (array_diff($plan['regular_seats'], $occupied) as $seat) {
            $employeeId = $this->drawRegularEmployee($pool);

            if ($employeeId === null) {
                return;
            }

            $passenger = $this->planPassenger(
                $plan['schedule'],
                $plan['departure_at']->startOfDay(),
                $plan['departure_at'],
                $now,
                $employeeId,
                (int) $seat,
                ShuttleReservation::SOURCE_SELECTED,
                $plan['status'],
                $plan,
            );
            $plan['passengers'][] = $passenger;
        }

        for ($position = 0; $position < 3; $position++) {
            $employeeId = $this->drawRegularEmployee($pool);

            if ($employeeId === null) {
                break;
            }

            $queuedAt = $this->bookingMoment($plan['departure_at'], $now);
            $plan['waitlist'][] = ['employee_id' => $employeeId, 'queued_at' => $queuedAt];
            $plan['events'][] = $this->activityEvent(
                $plan['schedule'],
                $plan['departure_at']->startOfDay(),
                $employeeId,
                ShuttleActivityEventType::WaitlistJoined,
                null,
                $queuedAt,
            );
        }

        $plans[$candidateIndex] = $plan;
    }

    /**
     * Puts a persona on a service. A free seat they are entitled to is used when
     * one is left; otherwise a filler employee gives theirs up.
     *
     * @param  list<array<string, mixed>>  $plans
     * @param  callable(array<string, mixed>): bool  $matches
     */
    private function placePersona(
        array &$plans,
        string $email,
        CarbonImmutable $now,
        callable $matches,
        bool $forceBoarded = false,
    ): void {
        $employeeId = $this->personaId($email);

        if ($employeeId === null) {
            return;
        }

        $isPriority = $this->employees[$employeeId]['priority'] === Employee::PRIORITY_STATUS_PRIORITY;

        foreach ($plans as $planIndex => $plan) {
            if (! $matches($plan)) {
                continue;
            }

            $eligibleSeats = $isPriority ? $plan['available_seats'] : $plan['regular_seats'];
            $freeSeats = array_values(array_diff(
                $eligibleSeats,
                array_column($plan['passengers'], 'seat')
            ));

            if ($freeSeats !== []) {
                $this->placedPersonaIds[] = $employeeId;
                $passenger = $this->planPassenger(
                    $plan['schedule'],
                    $plan['departure_at']->startOfDay(),
                    $plan['departure_at'],
                    $now,
                    $employeeId,
                    (int) $freeSeats[0],
                    ShuttleReservation::SOURCE_SELECTED,
                    $forceBoarded ? null : $plan['status'],
                    $plan,
                );
                $plan['passengers'][] = $passenger;

                if ($forceBoarded) {
                    $lastIndex = array_key_last($plan['passengers']);
                    $plan['passengers'][$lastIndex] = [
                        ...$plan['passengers'][$lastIndex],
                        ...$this->boardedAttendance($plan['departure_at'], $now),
                    ];
                }

                $plans[$planIndex] = $plan;

                return;
            }

            foreach ($plan['passengers'] as $passengerIndex => $passenger) {
                if (
                    ! $isPriority
                    && in_array($passenger['seat'], $plan['priority_seats'], true)
                ) {
                    continue;
                }

                if (in_array($passenger['employee_id'], $this->placedPersonaIds, true)) {
                    continue;
                }

                $this->placedPersonaIds[] = $employeeId;
                $plans[$planIndex]['passengers'][$passengerIndex]['employee_id'] = $employeeId;

                if ($forceBoarded) {
                    $plans[$planIndex]['passengers'][$passengerIndex] = [
                        ...$plans[$planIndex]['passengers'][$passengerIndex],
                        ...$this->boardedAttendance($plan['departure_at'], $now),
                    ];
                }

                $this->reassignEvents(
                    $plans[$planIndex],
                    $passenger['employee_id'],
                    $employeeId
                );

                return;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function queuePersona(array &$plans, string $email): void
    {
        $employeeId = $this->personaId($email);

        if ($employeeId === null) {
            return;
        }

        foreach ($plans as $planIndex => $plan) {
            if ($plan['waitlist'] === [] || $plan['status'] === null) {
                continue;
            }

            if ($plan['status']->isFinalized()) {
                continue;
            }

            $replaced = $plan['waitlist'][0]['employee_id'];
            $plans[$planIndex]['waitlist'][0]['employee_id'] = $employeeId;
            $this->reassignEvents($plans[$planIndex], $replaced, $employeeId);

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function reassignEvents(array &$plan, int $fromEmployeeId, int $toEmployeeId): void
    {
        foreach ($plan['events'] as $index => $event) {
            if ($event['employee_id'] !== $fromEmployeeId) {
                continue;
            }

            $plan['events'][$index] = [
                ...$event,
                'employee_id' => $toEmployeeId,
                'employee_id_snapshot' => $toEmployeeId,
                'employee_code_snapshot' => $this->employees[$toEmployeeId]['code'],
                'employee_name' => $this->employees[$toEmployeeId]['name'],
                'employee_priority_status' => $this->employees[$toEmployeeId]['priority'],
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function persistDay(
        CarbonImmutable $date,
        array &$plans,
        bool $materializeServices,
    ): void {
        if ($materializeServices) {
            $this->insertOccurrences($date, $plans);
        }

        $occurrenceIds = $materializeServices
            ? DB::table('shuttle_service_occurrences')
                ->where('travel_date', $date->toDateString())
                ->pluck('id', 'shuttle_schedule_id')
                ->all()
            : [];

        $this->insertReservations($date, $plans);

        $reservationIds = DB::table('shuttle_reservations')
            ->where('travel_date', $date->toDateString())
            ->get(['id', 'shuttle_schedule_id', 'seat_number'])
            ->mapWithKeys(fn (object $row): array => [
                $row->shuttle_schedule_id.':'.$row->seat_number => (int) $row->id,
            ])
            ->all();

        $this->insertAttendances($plans, $occurrenceIds, $reservationIds);
        $this->insertWaitlistEntries($date, $plans);
        $this->insertActivityEvents($plans, $occurrenceIds, $reservationIds);
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function insertOccurrences(CarbonImmutable $date, array &$plans): void
    {
        $rows = [];

        foreach ($plans as &$plan) {
            $schedule = $plan['schedule'];
            $status = $plan['status'];
            $boarded = $this->countAttendance($plan, ServiceAttendanceStatus::Boarded);
            $noShows = $this->countAttendance($plan, ServiceAttendanceStatus::NoShow);
            $closeout = $this->closeoutValues($plan);

            /* Finalizing a run empties its waitlist into unserved events. */
            if ($status?->isFinalized() === true) {
                $plan['unserved_waitlist'] = $plan['waitlist'];
                $plan['waitlist'] = [];
            }

            $rows[] = [
                'shuttle_schedule_id' => $schedule->getKey(),
                'travel_date' => $date->toDateString(),
                'route_id' => $schedule->route_id,
                'vehicle_id' => $schedule->vehicle_id,
                'driver_id' => $schedule->driver_id,
                'route_name' => $schedule->route->name,
                'origin' => $schedule->route->origin,
                'destination' => $schedule->route->destination,
                'direction' => $schedule->direction,
                'plate_number' => $schedule->vehicle->plate_number,
                'vehicle_type' => $schedule->vehicle->vehicle_type,
                'driver_name' => $schedule->driver->name,
                'driver_employee_id' => $schedule->driver->employee_id,
                'departure_time' => (string) $schedule->departure_time,
                'scheduled_departure_at' => $plan['departure_at']->format('Y-m-d H:i:s'),
                'effective_capacity' => $plan['effective_capacity'],
                'available_capacity' => count($plan['available_seats']),
                'priority_seats' => json_encode(
                    $this->seatPolicy->effectivePrioritySeats($schedule)
                ),
                'unavailable_seats' => json_encode(
                    $this->seatPolicy->effectiveUnavailableSeats($schedule)
                ),
                'waitlist_enabled' => (bool) $schedule->waitlist_enabled,
                'waitlist_capacity' => $schedule->waitlist_capacity,
                'status' => $status->value,
                'reservation_count' => count($plan['passengers']),
                'boarded_count' => $status === ServiceOccurrenceStatus::NotOperated ? 0 : $boarded,
                'no_show_count' => $status === ServiceOccurrenceStatus::Completed ? $noShows : 0,
                'unserved_waitlist_count' => count($plan['unserved_waitlist']),
                ...$closeout,
                'created_at' => $plan['departure_at']->subHours(6)->format('Y-m-d H:i:s'),
                'updated_at' => ($closeout['finalized_at'] ?? null)
                    ?? $plan['departure_at']->format('Y-m-d H:i:s'),
            ];
        }

        unset($plan);
        $this->insertChunked('shuttle_service_occurrences', $rows);
    }

    /**
     * Odometer readings, punctuality and closeout notes for a finalized run.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function closeoutValues(array $plan): array
    {
        $status = $plan['status'];
        $empty = [
            'opening_odometer_km' => null,
            'closing_odometer_km' => null,
            'distance_km' => null,
            'actual_departure_at' => null,
            'actual_arrival_at' => null,
            'operational_notes' => null,
            'incident_notes' => null,
            'not_operated_reason' => null,
            'finalized_at' => null,
            'finalized_by' => null,
            'finalized_by_id_snapshot' => null,
            'finalized_by_name' => null,
        ];

        if ($status === null || ! $status->isFinalized()) {
            return $empty;
        }

        $administrator = $this->administrators[array_rand($this->administrators)];
        $now = CarbonImmutable::now($plan['departure_at']->getTimezone());

        if ($status === ServiceOccurrenceStatus::NotOperated) {
            return [
                ...$empty,
                'operational_notes' => mt_rand(1, 100) <= 40
                    ? 'Employees were advised through the shuttle group chat.'
                    : null,
                'not_operated_reason' => $this->notOperatedReason(),
                'finalized_at' => $this->cap(
                    $plan['departure_at']->addMinutes(mt_rand(20, 180)),
                    $now
                )->format('Y-m-d H:i:s'),
                'finalized_by' => $administrator['id'],
                'finalized_by_id_snapshot' => $administrator['id'],
                'finalized_by_name' => $administrator['name'],
            ];
        }

        $vehicleId = (int) $plan['schedule']->vehicle_id;
        $distance = round(
            $this->routeDistance($plan['schedule']->route->destination)
                * $this->randomFloat(0.88, 1.2),
            1
        );
        $opening = round($this->vehicleOdometer[$vehicleId] ?? 60000.0, 1);
        $closing = round($opening + $distance, 1);
        $this->vehicleOdometer[$vehicleId] = $closing;

        $actualDeparture = $plan['departure_at']->addMinutes($this->departureDelayMinutes());
        $actualArrival = $actualDeparture->addMinutes(
            (int) round($distance / 22 * 60) + mt_rand(12, 45)
        );
        $actualArrival = $this->cap($actualArrival, $now);

        if ($actualArrival->lessThanOrEqualTo($actualDeparture)) {
            $actualArrival = $actualDeparture->addMinutes(5);
        }

        return [
            'opening_odometer_km' => $opening,
            'closing_odometer_km' => $closing,
            'distance_km' => $distance,
            'actual_departure_at' => $actualDeparture->format('Y-m-d H:i:s'),
            'actual_arrival_at' => $actualArrival->format('Y-m-d H:i:s'),
            'operational_notes' => mt_rand(1, 100) <= 22 ? $this->operationalNote() : null,
            'incident_notes' => mt_rand(1, 100) <= 7 ? $this->incidentNote() : null,
            'not_operated_reason' => null,
            'finalized_at' => $this->cap(
                $actualArrival->addMinutes(mt_rand(10, 90)),
                $now
            )->format('Y-m-d H:i:s'),
            'finalized_by' => $administrator['id'],
            'finalized_by_id_snapshot' => $administrator['id'],
            'finalized_by_name' => $administrator['name'],
        ];
    }

    private function departureDelayMinutes(): int
    {
        return match (true) {
            mt_rand(1, 100) <= 5 => -mt_rand(1, 3),
            mt_rand(1, 100) <= 62 => mt_rand(0, 4),
            mt_rand(1, 100) <= 80 => mt_rand(5, 15),
            default => mt_rand(16, 40),
        };
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function countAttendance(array $plan, ServiceAttendanceStatus $status): int
    {
        return count(array_filter(
            $plan['passengers'],
            fn (array $passenger): bool => $passenger['attendance'] === $status
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function insertReservations(CarbonImmutable $date, array $plans): void
    {
        $rows = [];

        foreach ($plans as $plan) {
            foreach ($plan['passengers'] as $passenger) {
                $rows[] = [
                    'employee_id' => $passenger['employee_id'],
                    'shuttle_schedule_id' => $plan['schedule']->getKey(),
                    'travel_date' => $date->toDateString(),
                    'seat_number' => $passenger['seat'],
                    'source' => $passenger['source'],
                    'reserved_at' => $passenger['reserved_at']->format('Y-m-d H:i:s'),
                    'created_at' => $passenger['reserved_at']->format('Y-m-d H:i:s'),
                    'updated_at' => $passenger['reserved_at']->format('Y-m-d H:i:s'),
                ];
            }
        }

        $this->insertChunked('shuttle_reservations', $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @param  array<int, int>  $occurrenceIds
     * @param  array<string, int>  $reservationIds
     */
    private function insertAttendances(
        array $plans,
        array $occurrenceIds,
        array $reservationIds,
    ): void {
        $rows = [];

        foreach ($plans as $plan) {
            $scheduleId = (int) $plan['schedule']->getKey();
            $occurrenceId = $occurrenceIds[$scheduleId] ?? null;

            if ($occurrenceId === null) {
                continue;
            }

            $administrator = $this->administrators[array_rand($this->administrators)];

            foreach ($plan['passengers'] as $passenger) {
                if ($passenger['attendance'] === null) {
                    continue;
                }

                $employee = $this->employees[$passenger['employee_id']];
                $recordedAt = $passenger['boarded_at'] ?? $plan['departure_at'];
                $rows[] = [
                    'shuttle_service_occurrence_id' => $occurrenceId,
                    'shuttle_reservation_id' => $reservationIds[$scheduleId.':'.$passenger['seat']] ?? null,
                    'employee_id' => $passenger['employee_id'],
                    'employee_id_snapshot' => $passenger['employee_id'],
                    'employee_code_snapshot' => $employee['code'],
                    'employee_name' => $employee['name'],
                    'department' => $employee['department'],
                    'priority_status' => $employee['priority'],
                    'seat_number' => $passenger['seat'],
                    'status' => $passenger['attendance']->value,
                    'recording_method' => $passenger['recording_method']->value,
                    'boarded_at' => $passenger['boarded_at']?->format('Y-m-d H:i:s'),
                    'recorded_by' => $administrator['id'],
                    'recorded_by_id_snapshot' => $administrator['id'],
                    'recorded_by_name' => $administrator['name'],
                    'created_at' => $recordedAt->format('Y-m-d H:i:s'),
                    'updated_at' => $recordedAt->format('Y-m-d H:i:s'),
                ];
            }
        }

        $this->insertChunked('shuttle_service_attendances', $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private function insertWaitlistEntries(CarbonImmutable $date, array $plans): void
    {
        $rows = [];

        foreach ($plans as $plan) {
            foreach ($plan['waitlist'] as $entry) {
                $rows[] = [
                    'employee_id' => $entry['employee_id'],
                    'shuttle_schedule_id' => $plan['schedule']->getKey(),
                    'travel_date' => $date->toDateString(),
                    'queued_at' => $entry['queued_at']->format('Y-m-d H:i:s'),
                    'created_at' => $entry['queued_at']->format('Y-m-d H:i:s'),
                    'updated_at' => $entry['queued_at']->format('Y-m-d H:i:s'),
                ];
            }
        }

        $this->insertChunked('shuttle_waitlist_entries', $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @param  array<int, int>  $occurrenceIds
     * @param  array<string, int>  $reservationIds
     */
    private function insertActivityEvents(
        array $plans,
        array $occurrenceIds,
        array $reservationIds,
    ): void {
        $rows = [];

        foreach ($plans as $plan) {
            $scheduleId = (int) $plan['schedule']->getKey();
            $occurrenceId = $occurrenceIds[$scheduleId] ?? null;
            $events = $plan['events'];

            /* Closing out a run turns the remaining queue into unserved events. */
            foreach ($plan['unserved_waitlist'] as $entry) {
                $events[] = $this->activityEvent(
                    $plan['schedule'],
                    $plan['departure_at']->startOfDay(),
                    $entry['employee_id'],
                    ShuttleActivityEventType::WaitlistUnserved,
                    null,
                    $plan['departure_at']->addMinutes(mt_rand(5, 60)),
                    [
                        'queued_at' => $entry['queued_at']->toIso8601String(),
                        'service_status' => $plan['status']->value,
                    ],
                );
            }

            foreach ($events as $event) {
                $seat = $event['seat_number'];
                $metadata = $event['metadata'];

                if ($seat !== null && $this->linksToReservation($event['event_type'])) {
                    $metadata['reservation_id'] = $reservationIds[$scheduleId.':'.$seat] ?? null;
                }

                $rows[] = [
                    ...$event,
                    'shuttle_service_occurrence_id' => $occurrenceId,
                    'metadata' => $metadata === [] ? null : json_encode($metadata),
                ];
            }
        }

        $this->insertChunked('shuttle_activity_events', $rows);
    }

    /**
     * Events for a seat that is still held can point at the reservation row; a
     * cancellation cannot, because the row it referred to no longer exists.
     */
    private function linksToReservation(string $eventType): bool
    {
        return in_array($eventType, [
            ShuttleActivityEventType::ReservationCreated->value,
            ShuttleActivityEventType::ReservationSeatChanged->value,
            ShuttleActivityEventType::WaitlistPromoted->value,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function activityEvent(
        ShuttleSchedule $schedule,
        CarbonImmutable $date,
        int $employeeId,
        ShuttleActivityEventType $eventType,
        ?int $seatNumber,
        CarbonImmutable $occurredAt,
        array $metadata = [],
    ): array {
        $employee = $this->employees[$employeeId];

        return [
            'employee_id' => $employeeId,
            'employee_id_snapshot' => $employeeId,
            'employee_code_snapshot' => $employee['code'],
            'shuttle_schedule_id' => $schedule->getKey(),
            'shuttle_schedule_id_snapshot' => $schedule->getKey(),
            'shuttle_service_occurrence_id' => null,
            'route_id_snapshot' => $schedule->route_id,
            'route_name' => $schedule->route->name,
            'direction' => $schedule->direction,
            'departure_time' => (string) $schedule->departure_time,
            'vehicle_id_snapshot' => $schedule->vehicle_id,
            'plate_number' => $schedule->vehicle->plate_number,
            'driver_id_snapshot' => $schedule->driver_id,
            'driver_name' => $schedule->driver->name,
            'driver_employee_id' => $schedule->driver->employee_id,
            'travel_date' => $date->toDateString(),
            'event_type' => $eventType->value,
            'seat_number' => $seatNumber,
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
            'employee_name' => $employee['name'],
            'employee_priority_status' => $employee['priority'],
            'metadata' => $metadata,
        ];
    }

    /**
     * Amendments to already-finalized runs, plus a few services that were
     * reopened and are waiting to be closed again.
     */
    private function seedCorrections(CarbonImmutable $now): void
    {
        $completed = DB::table('shuttle_service_occurrences')
            ->where('status', ServiceOccurrenceStatus::Completed->value)
            ->where('travel_date', '<', $now->toDateString())
            ->inRandomOrder()
            ->limit(12)
            ->get();
        $rows = [];

        foreach ($completed as $occurrence) {
            $administrator = $this->administrators[array_rand($this->administrators)];
            [$reason, $before, $after] = $this->correctionNarrative($occurrence);
            $correctedAt = CarbonImmutable::parse($occurrence->scheduled_departure_at)
                ->addDays(mt_rand(1, 4))
                ->setTime(mt_rand(9, 16), mt_rand(0, 59));

            $rows[] = [
                'shuttle_service_occurrence_id' => $occurrence->id,
                'corrected_by' => $administrator['id'],
                'corrected_by_id_snapshot' => $administrator['id'],
                'corrected_by_name' => $administrator['name'],
                'action' => 'CORRECTION',
                'reason' => $reason,
                'before_values' => json_encode($before),
                'after_values' => json_encode($after),
                'corrected_at' => $this->cap($correctedAt, $now)->format('Y-m-d H:i:s'),
                'created_at' => $this->cap($correctedAt, $now)->format('Y-m-d H:i:s'),
                'updated_at' => $this->cap($correctedAt, $now)->format('Y-m-d H:i:s'),
            ];
        }

        foreach ($this->reopenedOccurrences($now) as $occurrence) {
            $administrator = $this->administrators[array_rand($this->administrators)];
            $reopenedAt = $this->cap(
                CarbonImmutable::parse($occurrence->scheduled_departure_at)->addHours(mt_rand(6, 30)),
                $now
            );

            $rows[] = [
                'shuttle_service_occurrence_id' => $occurrence->id,
                'corrected_by' => $administrator['id'],
                'corrected_by_id_snapshot' => $administrator['id'],
                'corrected_by_name' => $administrator['name'],
                'action' => 'REOPEN',
                'reason' => 'Reopened after the driver reported the closeout was filed against the wrong run.',
                'before_values' => json_encode([
                    'status' => ServiceOccurrenceStatus::Completed->value,
                    'no_show_count' => (int) $occurrence->reservation_count - (int) $occurrence->boarded_count,
                    'finalized_by_name' => $administrator['name'],
                ]),
                'after_values' => json_encode([
                    'status' => ServiceOccurrenceStatus::AwaitingCompletion->value,
                    'no_show_count' => 0,
                    'finalized_by_name' => null,
                ]),
                'corrected_at' => $reopenedAt->format('Y-m-d H:i:s'),
                'created_at' => $reopenedAt->format('Y-m-d H:i:s'),
                'updated_at' => $reopenedAt->format('Y-m-d H:i:s'),
            ];
        }

        $this->insertChunked('shuttle_service_corrections', $rows);
    }

    /**
     * Runs left open by a reopen: their no-show rows are gone and the closeout
     * fields are cleared, exactly as the closeout service leaves them.
     *
     * @return Collection<int, object>
     */
    private function reopenedOccurrences(CarbonImmutable $now)
    {
        $occurrences = DB::table('shuttle_service_occurrences')
            ->where('status', ServiceOccurrenceStatus::AwaitingCompletion->value)
            ->where('travel_date', '<', $now->toDateString())
            ->where('reservation_count', '>', 0)
            ->inRandomOrder()
            ->limit(3)
            ->get();

        foreach ($occurrences as $occurrence) {
            DB::table('shuttle_service_attendances')
                ->where('shuttle_service_occurrence_id', $occurrence->id)
                ->where('status', ServiceAttendanceStatus::NoShow->value)
                ->delete();
        }

        return $occurrences;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function correctionNarrative(object $occurrence): array
    {
        $closing = (float) $occurrence->closing_odometer_km;
        $opening = (float) $occurrence->opening_odometer_km;

        return match (mt_rand(1, 3)) {
            1 => [
                'Closing odometer was transcribed with a digit missing; corrected against the trip ticket.',
                [
                    'closing_odometer_km' => number_format($closing - 9.0, 1, '.', ''),
                    'distance_km' => number_format($closing - $opening - 9.0, 1, '.', ''),
                ],
                [
                    'closing_odometer_km' => number_format($closing, 1, '.', ''),
                    'distance_km' => number_format($closing - $opening, 1, '.', ''),
                ],
            ],
            2 => [
                'Actual arrival was logged after the driver returned the tablet; timestamp corrected.',
                ['actual_arrival_at' => null],
                [
                    'actual_arrival_at' => CarbonImmutable::parse($occurrence->actual_arrival_at)
                        ->toIso8601String(),
                ],
            ],
            default => [
                'Passenger boarded but the scanner failed; attendance amended from no-show to boarded.',
                [
                    'boarded_count' => max(0, (int) $occurrence->boarded_count - 1),
                    'no_show_count' => (int) $occurrence->no_show_count + 1,
                ],
                [
                    'boarded_count' => (int) $occurrence->boarded_count,
                    'no_show_count' => (int) $occurrence->no_show_count,
                ],
            ],
        };
    }

    private function notOperatedReason(): string
    {
        return [
            'Vehicle broke down before dispatch and no spare unit was available.',
            'Assigned driver called in sick and no reliever could be rostered in time.',
            'Trip cancelled: typhoon signal number 2 was raised over Metro Manila.',
            'Suspension of work declared by the Office of the President.',
            'Road closure along the route for an emergency utility repair.',
            'No passengers reserved for this departure.',
        ][mt_rand(0, 5)];
    }

    private function operationalNote(): string
    {
        return [
            'Heavy traffic along EDSA; arrival delayed but all passengers were dropped off.',
            'Detoured through an alternate route because of flooding.',
            'Departed slightly late while waiting for a late-arriving priority passenger.',
            'Aircon serviced before dispatch; unit ran normally for the whole trip.',
            'Two passengers boarded at the secondary pickup point as approved.',
        ][mt_rand(0, 4)];
    }

    private function incidentNote(): string
    {
        return [
            'Minor fender bender at the exit ramp; no injuries, incident report filed.',
            'Passenger felt unwell mid-trip and was dropped off at the nearest clinic.',
            'Flat tyre replaced en route; trip resumed after 25 minutes.',
            'Unauthorised passenger attempted to board and was politely refused.',
            'Aircon failed halfway through; maintenance ticket raised for the unit.',
        ][mt_rand(0, 4)];
    }

    private function routeDistance(string $destination): float
    {
        return self::ROUTE_DISTANCES_KM[$destination] ?? 18.0;
    }

    /**
     * A booking moment somewhere in the days before departure, never in the
     * future and never after the shuttle has left.
     */
    private function bookingMoment(
        CarbonImmutable $departureAt,
        CarbonImmutable $now,
    ): CarbonImmutable {
        $latest = $departureAt->lessThan($now) ? $departureAt : $now;

        return $latest->subMinutes(mt_rand(20, 5 * 24 * 60));
    }

    private function between(CarbonImmutable $start, CarbonImmutable $end): CarbonImmutable
    {
        $span = (int) $start->diffInMinutes($end, absolute: true);

        return $span <= 1 ? $start : $start->addMinutes(mt_rand(1, $span));
    }

    private function cap(CarbonImmutable $moment, CarbonImmutable $ceiling): CarbonImmutable
    {
        return $moment->greaterThan($ceiling) ? $ceiling : $moment;
    }

    private function randomFloat(float $minimum, float $maximum): float
    {
        return $minimum + (mt_rand(0, 1000) / 1000) * ($maximum - $minimum);
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $roll = mt_rand(1, max(1, array_sum($weights)));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
