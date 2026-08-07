<?php

namespace Database\Seeders;

use App\Enums\EmployeeLoginMethod;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Portal sign-ins behind the employee access log.
 *
 * Most sign-ins belong to someone who is travelling that day and happen shortly
 * before their shuttle leaves; the rest are employees who only checked the
 * schedule. Both credentials are represented, weighted towards the QR code.
 */
class EmployeeLoginLogSeeder extends Seeder
{
    private const RANDOM_SEED = 20260808;

    private const INSERT_CHUNK = 500;

    /** Share of that day's passengers who signed in to the portal. */
    private const RIDER_SIGN_IN_RATE = 55;

    /** Share of the roster who signed in without booking anything. */
    private const BROWSER_SIGN_IN_RATE = 7;

    /**
     * Run the database seeds.
     *
     * @param  int  $historyDays  How far back sign-ins are generated.
     */
    public function run(int $historyDays = 60): void
    {
        mt_srand(self::RANDOM_SEED);

        $timezone = (string) config('shuttle.operating_timezone', 'Asia/Manila');
        $now = CarbonImmutable::now($timezone);
        $today = $now->startOfDay();
        $employees = Employee::query()
            ->get(['employee_id', 'employee_code', 'name', 'department', 'priority_status', 'status'])
            ->keyBy('employee_id');

        if ($employees->isEmpty()) {
            return;
        }

        $activeIds = $employees
            ->where('status', Employee::STATUS_ACTIVE)
            ->keys()
            ->all();
        $rows = [];

        for ($offset = -$historyDays; $offset <= 0; $offset++) {
            $date = $today->addDays($offset);

            foreach ($this->signInsFor($date, $now, $activeIds) as [$employeeId, $signedInAt]) {
                $employee = $employees->get($employeeId);

                if ($employee === null) {
                    continue;
                }

                $rows[] = [
                    'employee_id' => $employee->getKey(),
                    'employee_id_snapshot' => $employee->getKey(),
                    'employee_code_snapshot' => $employee->employee_code,
                    'employee_name' => $employee->name,
                    'department' => $employee->department,
                    'priority_status' => $employee->priority_status,
                    'login_method' => mt_rand(1, 100) <= 65
                        ? EmployeeLoginMethod::QrScan->value
                        : EmployeeLoginMethod::EmployeeCode->value,
                    'logged_in_at' => $signedInAt->format('Y-m-d H:i:s'),
                ];
            }
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            DB::table('employee_login_logs')->insert($chunk);
        }
    }

    /**
     * @param  list<int>  $activeIds
     * @return list<array{0: int, 1: CarbonImmutable}>
     */
    private function signInsFor(
        CarbonImmutable $date,
        CarbonImmutable $now,
        array $activeIds,
    ): array {
        $signIns = [];
        $passengers = DB::table('shuttle_reservations')
            ->join(
                'shuttle_schedules',
                'shuttle_schedules.id',
                '=',
                'shuttle_reservations.shuttle_schedule_id'
            )
            ->where('shuttle_reservations.travel_date', $date->toDateString())
            ->get([
                'shuttle_reservations.employee_id',
                'shuttle_schedules.departure_time',
            ]);

        foreach ($passengers as $passenger) {
            if (mt_rand(1, 100) > self::RIDER_SIGN_IN_RATE) {
                continue;
            }

            $departureAt = CarbonImmutable::parse(
                $date->toDateString().' '.$passenger->departure_time,
                $now->getTimezone()
            );
            $signedInAt = $departureAt->subMinutes(mt_rand(10, 300));

            if ($signedInAt->greaterThan($now)) {
                continue;
            }

            $signIns[] = [(int) $passenger->employee_id, $signedInAt];

            /* A second look at the schedule later in the day. */
            if (mt_rand(1, 100) <= 15) {
                $secondSignIn = $signedInAt->subMinutes(mt_rand(60, 600));
                $signIns[] = [(int) $passenger->employee_id, $secondSignIn];
            }
        }

        foreach ($activeIds as $employeeId) {
            if (mt_rand(1, 100) > self::BROWSER_SIGN_IN_RATE) {
                continue;
            }

            $signedInAt = $date->setTime(mt_rand(7, 18), mt_rand(0, 59), mt_rand(0, 59));

            if ($signedInAt->greaterThan($now)) {
                continue;
            }

            $signIns[] = [$employeeId, $signedInAt];
        }

        return $signIns;
    }
}
