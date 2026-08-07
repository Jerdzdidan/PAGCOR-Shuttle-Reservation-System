<?php

namespace Database\Seeders;

use App\Enums\UserLogAction;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Employee;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Back-fills a plausible user log.
 *
 * The log is normally written by the RecordsUserActivity trait during a real
 * request, which needs a signed-in user; a seeder has none. These rows are
 * therefore composed directly, in the same shape the trait produces.
 */
class UserLogSeeder extends Seeder
{
    private const RANDOM_SEED = 20260809;

    private const HISTORY_DAYS = 45;

    private const ENTRY_COUNT = 90;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        mt_srand(self::RANDOM_SEED);

        DB::table('user_logs')->delete();

        $users = User::query()
            ->where('user_type', 'ADMIN')
            ->get(['id', 'name', 'email']);

        if ($users->isEmpty()) {
            return;
        }

        $now = CarbonImmutable::now(
            (string) config('shuttle.operating_timezone', 'Asia/Manila')
        );
        $stories = $this->stories();

        if ($stories === []) {
            return;
        }

        $rows = [];

        for ($entry = 0; $entry < self::ENTRY_COUNT; $entry++) {
            $user = $users->random();
            [$action, $subject, $before, $after] = $stories[array_rand($stories)]();
            $occurredAt = $now
                ->subDays(mt_rand(0, self::HISTORY_DAYS))
                ->setTime(mt_rand(8, 18), mt_rand(0, 59), mt_rand(0, 59));

            $rows[] = [
                'user_id' => $user->getKey(),
                'user_id_snapshot' => $user->getKey(),
                'user_name' => $user->name,
                'user_email' => $user->email,
                'action' => $action->value,
                'subject_type' => class_basename($subject),
                'subject_id' => $subject->getKey(),
                'subject_label' => $this->labelFor($subject),
                'before_values' => $before === [] ? null : json_encode($before),
                'after_values' => $after === [] ? null : json_encode($after),
                'ip_address' => sprintf('192.168.%d.%d', mt_rand(0, 4), mt_rand(2, 254)),
                'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_logs')->insert($chunk);
        }
    }

    /**
     * Change stories, each returning the action, the record it touched, and the
     * fields that moved.
     *
     * @return list<callable(): array{0: UserLogAction, 1: Model, 2: array<string, mixed>, 3: array<string, mixed>}>
     */
    private function stories(): array
    {
        $vehicles = Vehicle::query()->inRandomOrder()->limit(12)->get();
        $drivers = Driver::query()->inRandomOrder()->limit(12)->get();
        $routes = ShuttleRoute::query()->inRandomOrder()->limit(10)->get();
        $employees = Employee::query()->inRandomOrder()->limit(20)->get();
        $departments = Department::query()->inRandomOrder()->limit(6)->get();
        $schedules = ShuttleSchedule::query()->with('route:id,name')->inRandomOrder()->limit(10)->get();
        $stories = [];

        if ($vehicles->isNotEmpty()) {
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $vehicle = $vehicles->random(),
                ['status' => 'ACTIVE'],
                ['status' => $vehicle->status === 'ACTIVE' ? 'MAINTENANCE' : $vehicle->status],
            ];
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $vehicle = $vehicles->random(),
                ['capacity' => max(1, (int) $vehicle->capacity - 2), 'notes' => null],
                ['capacity' => (int) $vehicle->capacity, 'notes' => 'Seat count corrected after re-inspection.'],
            ];
        }

        if ($drivers->isNotEmpty()) {
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $driver = $drivers->random(),
                ['license_expires_at' => $driver->license_expires_at?->subYear()->toDateString()],
                ['license_expires_at' => $driver->license_expires_at?->toDateString()],
            ];
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $driver = $drivers->random(),
                ['status' => 'ACTIVE', 'notes' => null],
                ['status' => $driver->status, 'notes' => 'Roster status reviewed this month.'],
            ];
        }

        if ($routes->isNotEmpty()) {
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $route = $routes->random(),
                ['status' => 'ACTIVE'],
                ['status' => $route->status],
            ];
        }

        if ($employees->isNotEmpty()) {
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $employee = $employees->random(),
                ['priority_status' => 'REGULAR'],
                ['priority_status' => $employee->priority_status],
            ];
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $employee = $employees->random(),
                ['status' => 'ACTIVE'],
                ['status' => $employee->status],
            ];
            $stories[] = fn (): array => [
                UserLogAction::Created,
                $employee = $employees->random(),
                [],
                [
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'department' => $employee->department,
                    'position' => $employee->position,
                    'priority_status' => $employee->priority_status,
                ],
            ];
        }

        if ($departments->isNotEmpty()) {
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $department = $departments->random(),
                ['name' => $department->name.' (old)'],
                ['name' => $department->name],
            ];
        }

        if ($schedules->isNotEmpty()) {
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $schedule = $schedules->random(),
                ['capacity_override' => null],
                ['capacity_override' => $schedule->capacity_override],
            ];
            $stories[] = fn (): array => [
                UserLogAction::Updated,
                $schedule = $schedules->random(),
                ['waitlist_enabled' => true, 'waitlist_capacity' => null],
                [
                    'waitlist_enabled' => (bool) $schedule->waitlist_enabled,
                    'waitlist_capacity' => $schedule->waitlist_capacity,
                ],
            ];
            $stories[] = fn (): array => [
                UserLogAction::Deleted,
                $schedules->random(),
                [
                    'direction' => 'OUTBOUND',
                    'departure_time' => '21:30:00',
                    'status' => 'INACTIVE',
                    'notes' => 'Trial late run withdrawn after low demand.',
                ],
                [],
            ];
        }

        return $stories;
    }

    private function labelFor(Model $subject): string
    {
        return method_exists($subject, 'userLogLabel')
            ? $subject->userLogLabel()
            : class_basename($subject).' #'.$subject->getKey();
    }
}
