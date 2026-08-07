<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLoginLog;
use App\Models\ShuttleReservation;
use App\Models\ShuttleServiceAttendance;
use App\Models\ShuttleServiceOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the admin-facing activity trail for a single employee: how they signed in,
 * the services they have already travelled on, and what they currently hold.
 */
class EmployeeActivityData
{
    private const HISTORY_LIMIT = 50;

    /** @return array<string, mixed> */
    public function forEmployee(Employee $employee): array
    {
        $logins = $this->logins($employee);
        $boardings = $this->boardings($employee);
        $reservations = $this->reservations($employee);

        return [
            'employee' => [
                'employee_id' => $employee->getKey(),
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'email' => $employee->email,
                'department' => $employee->department,
                'position' => $employee->position,
                'priority_status' => $employee->priority_status,
                'status' => $employee->status,
            ],
            'summary' => [
                'total_logins' => EmployeeLoginLog::query()
                    ->where('employee_id_snapshot', $employee->getKey())
                    ->count(),
                'qr_logins' => EmployeeLoginLog::query()
                    ->where('employee_id_snapshot', $employee->getKey())
                    ->where('login_method', 'QR_SCAN')
                    ->count(),
                'last_login_at' => $logins->first()['logged_in_at'] ?? null,
                'boarded_count' => $boardings->where('status', 'BOARDED')->count(),
                'no_show_count' => $boardings->where('status', 'NO_SHOW')->count(),
                'upcoming_count' => $reservations->count(),
            ],
            'logins' => $logins->all(),
            'boardings' => $boardings->all(),
            'reservations' => $reservations->all(),
            'history_limit' => self::HISTORY_LIMIT,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function logins(Employee $employee): Collection
    {
        return EmployeeLoginLog::query()
            ->where('employee_id_snapshot', $employee->getKey())
            ->latest('logged_in_at')
            ->limit(self::HISTORY_LIMIT)
            ->get(['id', 'login_method', 'logged_in_at', 'department', 'priority_status'])
            ->map(fn (EmployeeLoginLog $login): array => [
                'id' => $login->getKey(),
                'logged_in_at' => $login->logged_in_at?->toIso8601String(),
                'method' => $login->login_method?->value,
                'method_label' => $login->login_method?->label() ?? 'Not recorded',
                'department' => $login->department,
                'priority_status' => $login->priority_status,
            ])
            ->values();
    }

    /**
     * Services the employee was marked against, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function boardings(Employee $employee): Collection
    {
        return ShuttleServiceAttendance::query()
            ->with('occurrence:id,travel_date,route_name,origin,destination,direction,plate_number,vehicle_type,driver_name,departure_time,scheduled_departure_at,status')
            ->where('employee_id_snapshot', $employee->getKey())
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(function (ShuttleServiceAttendance $attendance): array {
                /** @var ShuttleServiceOccurrence|null $occurrence */
                $occurrence = $attendance->occurrence;

                return [
                    'id' => $attendance->getKey(),
                    'travel_date' => $occurrence?->travel_date?->toDateString(),
                    'route_name' => $occurrence?->route_name,
                    'origin' => $occurrence?->origin,
                    'destination' => $occurrence?->destination,
                    'direction' => $occurrence?->direction,
                    'departure_time' => $occurrence === null
                        ? null
                        : mb_substr((string) $occurrence->departure_time, 0, 5),
                    'plate_number' => $occurrence?->plate_number,
                    'driver_name' => $occurrence?->driver_name,
                    'service_status' => $occurrence?->status?->value,
                    'seat_number' => $attendance->seat_number,
                    'status' => $attendance->status->value,
                    'recording_method' => $attendance->recording_method->value,
                    'boarded_at' => $attendance->boarded_at?->toIso8601String(),
                ];
            })
            ->sortByDesc(fn (array $item): string => (string) $item['travel_date'])
            ->values();
    }

    /**
     * Reservations that have not departed yet.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function reservations(Employee $employee): Collection
    {
        $operatingTimezone = (string) config('shuttle.operating_timezone', 'Asia/Manila');
        $now = CarbonImmutable::now($operatingTimezone);
        $occurrences = ShuttleServiceOccurrence::query()
            ->whereDate('travel_date', '>=', $now->startOfDay()->toDateString())
            ->get(['id', 'shuttle_schedule_id', 'travel_date', 'route_name', 'origin', 'destination', 'direction', 'plate_number', 'driver_name', 'departure_time', 'scheduled_departure_at', 'status'])
            ->keyBy(fn (ShuttleServiceOccurrence $occurrence): string => $occurrence->shuttle_schedule_id.'|'.$occurrence->travel_date->toDateString());

        return ShuttleReservation::query()
            ->with([
                'schedule:id,route_id,vehicle_id,driver_id,direction,departure_time',
                'schedule.route:id,name,origin,destination',
                'schedule.vehicle:id,plate_number',
                'schedule.driver:id,name',
            ])
            ->where('employee_id', $employee->getKey())
            ->whereDate('travel_date', '>=', $now->startOfDay()->toDateString())
            ->orderBy('travel_date')
            ->get()
            ->map(function (ShuttleReservation $reservation) use ($occurrences, $operatingTimezone): ?array {
                $schedule = $reservation->schedule;
                $travelDate = $reservation->travel_date->toDateString();
                $occurrence = $occurrences->get($reservation->shuttle_schedule_id.'|'.$travelDate);
                $departureTime = mb_substr(
                    (string) ($occurrence?->departure_time ?? $schedule?->departure_time),
                    0,
                    5
                );
                $departureAt = $occurrence?->scheduled_departure_at?->toImmutable()
                    ?? CarbonImmutable::parse($travelDate.' '.$departureTime, $operatingTimezone);

                if ($departureAt->lessThanOrEqualTo(CarbonImmutable::now($operatingTimezone))) {
                    return null;
                }

                return [
                    'id' => $reservation->getKey(),
                    'travel_date' => $travelDate,
                    'route_name' => $occurrence?->route_name ?? $schedule?->route?->name,
                    'origin' => $occurrence?->origin ?? $schedule?->route?->origin,
                    'destination' => $occurrence?->destination ?? $schedule?->route?->destination,
                    'direction' => $occurrence?->direction ?? $schedule?->direction,
                    'departure_time' => $departureTime,
                    'scheduled_departure_at' => $departureAt->toIso8601String(),
                    'plate_number' => $occurrence?->plate_number ?? $schedule?->vehicle?->plate_number,
                    'driver_name' => $occurrence?->driver_name ?? $schedule?->driver?->name,
                    'seat_number' => $reservation->seat_number,
                    'source' => $reservation->source,
                    'reserved_at' => $reservation->reserved_at?->toIso8601String(),
                    'service_status' => $occurrence?->status?->value,
                ];
            })
            ->filter()
            ->values();
    }
}
