<?php

namespace Database\Seeders;

use App\Enums\ServiceOccurrenceStatus;
use App\Models\Employee;
use App\Models\ShuttleServiceOccurrence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Builds a full demo environment: reference data, a departure roster covering
 * every scheduling edge case, and roughly two months of operating history plus
 * two weeks of forward bookings.
 *
 * Run it with `php artisan migrate:fresh --seed`.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ShuttleSettingSeeder::class,
            DepartmentSeeder::class,
            EmployeeSeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            ShuttleRouteSeeder::class,
            ShuttleScheduleSeeder::class,
            ShuttleOperationsSeeder::class,
            EmployeeLoginLogSeeder::class,
        ]);

        $this->reportSignInDetails();
        $this->reportDataVolume();
    }

    private function reportSignInDetails(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Administrator sign-in (/login)');
        $this->command->table(
            ['E-mail', 'Password', 'Role'],
            [
                [UserSeeder::ROOT_EMAIL, '123456', 'Administrator'],
                [UserSeeder::OPERATIONS_ADMIN_EMAIL, 'password', 'Administrator'],
                [UserSeeder::DISPATCH_ADMIN_EMAIL, 'password', 'Administrator'],
                [UserSeeder::AUDIT_ADMIN_EMAIL, 'password', 'Administrator'],
                [UserSeeder::NON_ADMIN_EMAIL, 'password', 'Not an administrator (403 on /admin)'],
            ],
        );

        $this->command->info('Employee sign-in (/employee/login) — use the employee ID below');
        $this->command->table(
            ['Employee ID', 'Name', 'What to expect'],
            $this->personaRows(),
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function personaRows(): array
    {
        $personas = [
            EmployeeSeeder::PERSONA_TODAY_UPCOMING => 'Holds a seat on a departure that has not left yet today.',
            EmployeeSeeder::PERSONA_TODAY_BOARDED => 'Already scanned onto a service awaiting closeout today.',
            EmployeeSeeder::PERSONA_TODAY_WAITLIST => 'Queued on a full shuttle today.',
            EmployeeSeeder::PERSONA_FUTURE_ONLY => 'Only holds a reservation for tomorrow.',
            EmployeeSeeder::PERSONA_NEVER_BOOKS => 'No bookings at all; clean slate for the booking flow.',
            EmployeeSeeder::PERSONA_PRIORITY_FREE => 'Priority tier, free to claim priority-only seats.',
            EmployeeSeeder::PERSONA_HEAVY_HISTORY => 'Long trip history for the activity and report screens.',
            EmployeeSeeder::PERSONA_FREQUENT_NO_SHOW => 'Misses shuttles often; skews the no-show reports.',
            EmployeeSeeder::PERSONA_DEACTIVATED => 'Deactivated: sign-in is refused.',
            EmployeeSeeder::PERSONA_DEACTIVATED_WITH_HISTORY => 'Deactivated after building up trip history.',
        ];
        $employees = Employee::query()
            ->whereIn('email', array_keys($personas))
            ->get(['employee_id', 'employee_code', 'name', 'email'])
            ->keyBy('email');
        $rows = [];

        foreach ($personas as $email => $expectation) {
            $employee = $employees->get($email);

            if ($employee === null) {
                continue;
            }

            $rows[] = [
                (string) $employee->employee_code,
                $employee->name,
                $expectation,
            ];
        }

        return $rows;
    }

    private function reportDataVolume(): void
    {
        if ($this->command === null) {
            return;
        }

        $statuses = ShuttleServiceOccurrence::query()
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->command->info('Seeded volume');
        $this->command->table(
            ['Records', 'Count'],
            [
                ['Employees', DB::table('employees')->count()],
                ['Shuttle schedules', DB::table('shuttle_schedules')->count()],
                ['Service runs', DB::table('shuttle_service_occurrences')->count()],
                ['  · scheduled', $statuses[ServiceOccurrenceStatus::Scheduled->value] ?? 0],
                ['  · awaiting closeout', $statuses[ServiceOccurrenceStatus::AwaitingCompletion->value] ?? 0],
                ['  · completed', $statuses[ServiceOccurrenceStatus::Completed->value] ?? 0],
                ['  · not operated', $statuses[ServiceOccurrenceStatus::NotOperated->value] ?? 0],
                ['Reservations', DB::table('shuttle_reservations')->count()],
                ['Open waitlist entries', DB::table('shuttle_waitlist_entries')->count()],
                ['Attendance records', DB::table('shuttle_service_attendances')->count()],
                ['Booking activity events', DB::table('shuttle_activity_events')->count()],
                ['Closeout corrections', DB::table('shuttle_service_corrections')->count()],
                ['Portal sign-ins', DB::table('employee_login_logs')->count()],
            ],
        );
        $this->command->comment(
            'The booking window is seeded disabled so the employee portal is reachable at any hour.'
        );
    }
}
