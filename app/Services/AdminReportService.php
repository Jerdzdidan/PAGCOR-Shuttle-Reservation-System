<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\ShuttleSchedule;
use App\Models\Vehicle;
use App\Services\Reports\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminReportService
{
    public const DEFAULT_REPORT = ReportCatalog::DEFAULT_REPORT;

    /** @return list<string> */
    public static function reportKeys(): array
    {
        return ReportCatalog::keys();
    }

    /** @return list<string> */
    public static function reportSlugs(): array
    {
        return ReportCatalog::slugs();
    }

    public static function reportKeyFromSlug(string $reportSlug): ?string
    {
        return ReportCatalog::keyFromSlug($reportSlug);
    }

    public static function reportSlugFromKey(string $reportKey): ?string
    {
        return ReportCatalog::slugFromKey($reportKey);
    }

    public static function defaultReportSlug(): string
    {
        return ReportCatalog::defaultSlug();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(array $filters): array
    {
        $filters = $this->normalize($filters);
        $definition = ReportCatalog::definition($filters['report']);
        $paginator = $this->sorted($this->query($filters), $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return [
            'report' => $definition,
            'title' => $definition['title'],
            'description' => $definition['answers'],
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'statusOptions' => ReportCatalog::statusOptions($filters['report']),
            'availableFilters' => ReportCatalog::filtersFor($filters['report']),
            'switcher' => ReportCatalog::switcher(),
            'period' => $this->period($filters),
            'kpis' => $this->kpis($filters),
            'chart' => $this->chart($filters),
            'columns' => $definition['columns'],
            'rows' => $this->paginator($paginator, $definition['columns'], $filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{headings: list<string>, rows: list<list<string|int|float|null>>, title: string, period: string}
     */
    public function export(array $filters): array
    {
        $filters = $this->normalize($filters);
        $definition = ReportCatalog::definition($filters['report']);
        $keys = array_column($definition['columns'], 'key');
        $rows = $this->decorate($this->sorted($this->query($filters), $filters)->get(), $filters);

        return [
            'headings' => array_column($definition['columns'], 'label'),
            'rows' => $rows->map(fn (object $row): array => array_map(
                fn (string $key): string|int|float|null => $this->value($row, $key),
                $keys
            ))->all(),
            'title' => $definition['title'],
            'period' => $this->period($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function query(array $filters): Builder
    {
        return match ($filters['report']) {
            'vehicle_utilization' => $this->operatedOccurrences($filters)
                ->selectRaw("COALESCE(plate_number, 'Unassigned') as vehicle, COALESCE(vehicle_type, '—') as vehicle_type, COUNT(DISTINCT travel_date) as service_days, COUNT(*) as services, SUM(COALESCE(distance_km, 0)) as distance_km, SUM(available_capacity) as seats_offered, SUM(boarded_count) as boarded, AVG(boarded_count) as avg_passengers, AVG(distance_km) as avg_distance")
                ->groupBy('plate_number', 'vehicle_type'),
            'route_demand' => $this->operatedOccurrences($filters)
                ->leftJoinSub($this->activityCounts($filters), 'activity_counts', 'activity_counts.occurrence_id', '=', 'shuttle_service_occurrences.id')
                ->selectRaw("COALESCE(route_name, 'Unassigned route') as route, direction, departure_time, COUNT(*) as services, SUM(reservation_count) as reservations, SUM(boarded_count) as boarded, SUM(no_show_count) as no_shows, SUM(available_capacity) as seats_offered, SUM(COALESCE(activity_counts.cancellations, 0)) as cancellations, SUM(COALESCE(activity_counts.waitlist_joined, 0)) as waitlist_joined, SUM(COALESCE(activity_counts.waitlist_promoted, 0)) as waitlist_promoted, SUM(unserved_waitlist_count) as waitlist_unserved")
                ->groupBy('route_name', 'direction', 'departure_time'),
            'driver_utilization' => $this->operatedOccurrences($filters)
                ->selectRaw("driver_id, COALESCE(driver_name, 'Unassigned') as driver, COALESCE(driver_employee_id, '—') as employee_id, COUNT(DISTINCT travel_date) as service_days, COUNT(*) as services, SUM(boarded_count) as boarded, SUM(available_capacity) as seats_offered, SUM(COALESCE(distance_km, 0)) as distance_km")
                ->groupBy('driver_id', 'driver_name', 'driver_employee_id'),
            'schedule_attendance' => $this->occurrences($filters)
                ->whereIn('status', ['AWAITING_COMPLETION', 'COMPLETED', 'NOT_OPERATED'])
                ->selectRaw("travel_date, COALESCE(route_name, 'Unassigned route') as route, direction, departure_time, COALESCE(plate_number, '—') as vehicle, COALESCE(driver_name, 'Unassigned') as driver, available_capacity as seats_offered, reservation_count as reserved, boarded_count as boarded, no_show_count as no_shows, unserved_waitlist_count as waitlist_unserved, status, scheduled_departure_at"),
            'passenger_manifest' => $this->attendance($filters)
                ->selectRaw("o.travel_date as service_date, COALESCE(o.route_name, 'Unassigned route') as route, o.direction, o.departure_time, COALESCE(a.employee_code_snapshot, a.employee_id_snapshot) as employee_id, a.employee_name as employee, COALESCE(a.department, '—') as department, a.priority_status as priority, a.seat_number as seat, a.status, a.recording_method as captured_by, COALESCE(a.boarded_at, a.created_at) as recorded_at, COALESCE(a.recorded_by_name, attendance_recorders.name, '—') as recorded_by"),
            'booking_activity' => $this->activities($filters)
                ->selectRaw("occurred_at, event_type as activity, COALESCE(employee_code_snapshot, employee_id_snapshot, '—') as employee_id, COALESCE(employee_name, '—') as employee, travel_date, COALESCE(route_name, 'Unassigned route') as route, COALESCE(direction, '—') as direction, departure_time, COALESCE(plate_number, '—') as vehicle, COALESCE(seat_number, '—') as seat, COALESCE(employee_priority_status, '—') as priority, metadata"),
            'closeout_corrections' => $this->corrections($filters)
                ->selectRaw("c.corrected_at, c.action, o.travel_date, COALESCE(o.route_name, 'Unassigned route') as route, COALESCE(o.plate_number, '—') as vehicle, o.status as service_status, c.reason, COALESCE(c.corrected_by_name, correction_admins.name, '—') as corrected_by, c.before_values, c.after_values"),
            'access_log' => $this->logins($filters)
                ->selectRaw("logged_in_at, COALESCE(employee_code_snapshot, employee_id_snapshot) as employee_id, employee_name as employee, COALESCE(department, '—') as department, priority_status as priority, login_method"),
            'incident_log' => $this->occurrences($filters)
                ->where(fn (Builder $query): Builder => $query->whereNotNull('incident_notes')->orWhereNotNull('operational_notes')->orWhere('status', 'NOT_OPERATED'))
                ->selectRaw("travel_date as service_date, COALESCE(route_name, 'Unassigned route') as route, direction, COALESCE(plate_number, '—') as vehicle, COALESCE(driver_name, 'Unassigned') as driver, status, operational_notes, incident_notes, not_operated_reason, scheduled_departure_at"),
            default => $this->occurrences($filters)
                ->leftJoin('users as finalizers', 'finalizers.id', '=', 'shuttle_service_occurrences.finalized_by')
                ->selectRaw("travel_date as service_date, COALESCE(route_name, 'Unassigned route') as route, direction, COALESCE(plate_number, '—') as vehicle, COALESCE(driver_name, 'Unassigned') as driver, scheduled_departure_at as scheduled_departure, actual_departure_at as actual_departure, reservation_count as reserved, boarded_count as boarded, no_show_count as no_shows, opening_odometer_km as opening_odometer, closing_odometer_km as closing_odometer, distance_km, status, COALESCE(shuttle_service_occurrences.finalized_by_name, finalizers.name, '—') as finalizer"),
        };
    }

    /**
     * Applies the requested ordering, falling back to the report's default.
     *
     * @param  array<string, mixed>  $filters
     */
    private function sorted(Builder $query, array $filters): Builder
    {
        $sort = $filters['sort'];
        $direction = $filters['direction'] === 'asc' ? 'asc' : 'desc';

        if ($sort === null || ! in_array($sort, ReportCatalog::columnKeys($filters['report']), true)) {
            $default = ReportCatalog::defaultSort($filters['report']);
            $sort = $default['key'];
            $direction = $default['direction'];
        }

        return $query->orderBy($this->sortExpression($filters['report'], $sort), $direction);
    }

    /**
     * Some columns are derived in PHP, so sorting falls back to the nearest stored column.
     */
    private function sortExpression(string $report, string $sort): string
    {
        return match (true) {
            $sort === 'departure_delay' => 'actual_departure',
            $sort === 'attendance_rate', $sort === 'load_factor' => 'boarded',
            $sort === 'waitlist_conversion' => 'waitlist_promoted',
            $sort === 'recorded_duration' => 'services',
            $sort === 'fields_changed' => 'corrected_at',
            $sort === 'cancellation_lead_time' => 'occurred_at',
            $report === 'incident_log' && $sort === 'service_date' => 'scheduled_departure_at',
            default => $sort,
        };
    }

    /** @param array<string, mixed> $filters */
    private function occurrences(array $filters): Builder
    {
        $query = DB::table('shuttle_service_occurrences')->whereBetween('travel_date', [$filters['date_from'], $filters['date_to']]);
        foreach (['route_id', 'vehicle_id', 'driver_id'] as $filter) {
            if ($filters[$filter] !== null) {
                $query->where($filter, $filters[$filter]);
            }
        }
        if ($filters['schedule_id'] !== null) {
            $query->where('shuttle_schedule_id', $filters['schedule_id']);
        }
        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function operatedOccurrences(array $filters): Builder
    {
        return $this->occurrences([
            ...$filters,
            'status' => null,
        ])->where('status', 'COMPLETED');
    }

    /** @param array<string, mixed> $filters */
    private function attendance(array $filters): Builder
    {
        $query = DB::table('shuttle_service_attendances as a')
            ->join('shuttle_service_occurrences as o', 'o.id', '=', 'a.shuttle_service_occurrence_id')
            ->leftJoin('users as attendance_recorders', 'attendance_recorders.id', '=', 'a.recorded_by')
            ->whereBetween('o.travel_date', [$filters['date_from'], $filters['date_to']]);
        foreach (['route_id', 'vehicle_id', 'driver_id'] as $filter) {
            if ($filters[$filter] !== null) {
                $query->where("o.{$filter}", $filters[$filter]);
            }
        }
        if ($filters['schedule_id'] !== null) {
            $query->where('o.shuttle_schedule_id', $filters['schedule_id']);
        }
        if ($filters['status'] !== null) {
            $query->where('a.status', $filters['status']);
        }
        if ($filters['department'] !== null) {
            $query->where('a.department', $filters['department']);
        }
        if ($filters['priority_status'] !== null) {
            $query->where('a.priority_status', $filters['priority_status']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function corrections(array $filters): Builder
    {
        $query = DB::table('shuttle_service_corrections as c')
            ->join('shuttle_service_occurrences as o', 'o.id', '=', 'c.shuttle_service_occurrence_id')
            ->leftJoin('users as correction_admins', 'correction_admins.id', '=', 'c.corrected_by')
            ->whereBetween('c.corrected_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59']);
        foreach (['route_id', 'vehicle_id', 'driver_id'] as $filter) {
            if ($filters[$filter] !== null) {
                $query->where("o.{$filter}", $filters[$filter]);
            }
        }
        if ($filters['status'] !== null) {
            $query->where('c.action', $filters['status']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function activityCounts(array $filters): Builder
    {
        $directEvents = DB::table('shuttle_activity_events as direct_events')
            ->join(
                'shuttle_service_occurrences as direct_occurrences',
                'direct_occurrences.id',
                '=',
                'direct_events.shuttle_service_occurrence_id'
            )
            ->whereNotNull('direct_events.shuttle_service_occurrence_id')
            ->whereBetween(
                'direct_events.travel_date',
                [$filters['date_from'], $filters['date_to']]
            )
            ->select([
                'direct_occurrences.id as occurrence_id',
                'direct_events.event_type',
            ]);
        $legacyEvents = DB::table('shuttle_activity_events as legacy_events')
            ->join(
                'shuttle_service_occurrences as legacy_occurrences',
                function ($join): void {
                    $join
                        ->on(
                            'legacy_occurrences.shuttle_schedule_id',
                            '=',
                            'legacy_events.shuttle_schedule_id_snapshot'
                        )
                        ->on(
                            'legacy_occurrences.travel_date',
                            '=',
                            'legacy_events.travel_date'
                        );
                }
            )
            ->whereNull('legacy_events.shuttle_service_occurrence_id')
            ->whereBetween(
                'legacy_events.travel_date',
                [$filters['date_from'], $filters['date_to']]
            )
            ->select([
                'legacy_occurrences.id as occurrence_id',
                'legacy_events.event_type',
            ]);

        foreach (['route_id', 'vehicle_id', 'driver_id'] as $filter) {
            if ($filters[$filter] !== null) {
                $directEvents->where(
                    "direct_occurrences.{$filter}",
                    $filters[$filter]
                );
                $legacyEvents->where(
                    "legacy_occurrences.{$filter}",
                    $filters[$filter]
                );
            }
        }

        return DB::query()
            ->fromSub(
                $directEvents->unionAll($legacyEvents),
                'matched_activity_events'
            )
            ->selectRaw("occurrence_id, SUM(CASE WHEN event_type = 'RESERVATION_CANCELLED' THEN 1 ELSE 0 END) as cancellations, SUM(CASE WHEN event_type = 'WAITLIST_JOINED' THEN 1 ELSE 0 END) as waitlist_joined, SUM(CASE WHEN event_type = 'WAITLIST_PROMOTED' THEN 1 ELSE 0 END) as waitlist_promoted")
            ->groupBy('occurrence_id');
    }

    /** @param array<string, mixed> $filters */
    private function activities(array $filters): Builder
    {
        $query = DB::table('shuttle_activity_events')->whereBetween('occurred_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59']);
        if ($filters['status'] !== null) {
            $query->where('event_type', $filters['status']);
        }
        if ($filters['priority_status'] !== null) {
            $query->where('employee_priority_status', $filters['priority_status']);
        }
        if ($filters['schedule_id'] !== null) {
            $query->where('shuttle_schedule_id_snapshot', $filters['schedule_id']);
        }
        foreach ([
            'route_id' => 'route_id_snapshot',
            'vehicle_id' => 'vehicle_id_snapshot',
            'driver_id' => 'driver_id_snapshot',
        ] as $filter => $column) {
            if ($filters[$filter] !== null) {
                $query->where($column, $filters[$filter]);
            }
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function logins(array $filters): Builder
    {
        $query = DB::table('employee_login_logs')->whereBetween('logged_in_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59']);
        if ($filters['department'] !== null) {
            $query->where('department', $filters['department']);
        }
        if ($filters['priority_status'] !== null) {
            $query->where('priority_status', $filters['priority_status']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters @return list<array{label: string, value: string|int, hint: string}> */
    private function kpis(array $filters): array
    {
        $rows = $this->query($filters)->get();

        return match ($filters['report']) {
            'vehicle_utilization' => [
                $this->kpi('Vehicles deployed', $rows->count(), 'Distinct vehicles with at least one completed trip.'),
                $this->kpi('Completed trips', (int) $rows->sum('services'), 'Service runs closed out as completed.'),
                $this->kpi('Distance', number_format((float) $rows->sum('distance_km'), 1).' km', 'Sum of closing minus opening odometer across completed trips.'),
                $this->kpi('Passengers', (int) $rows->sum('boarded'), 'Employees marked as boarded.'),
                $this->kpi('Load factor', $this->percentage((int) $rows->sum('boarded'), (int) $rows->sum('seats_offered')), 'Passengers boarded ÷ seats offered for booking.'),
            ],
            'route_demand' => [
                $this->kpi('Runs', (int) $rows->sum('services'), 'Completed service runs in the period.'),
                $this->kpi('Reserved', (int) $rows->sum('reservations'), 'Seats booked across those runs.'),
                $this->kpi('Boarded', (int) $rows->sum('boarded'), 'Employees marked as boarded.'),
                $this->kpi('Load factor', $this->percentage((int) $rows->sum('boarded'), (int) $rows->sum('seats_offered')), 'Passengers boarded ÷ seats offered for booking.'),
                $this->kpi('Cancellations', (int) $rows->sum('cancellations'), 'Reservations cancelled before departure.'),
                $this->kpi('Waitlist joins', (int) $rows->sum('waitlist_joined'), 'Employees who queued when no eligible seat was free.'),
                $this->kpi('Unserved', (int) $rows->sum('waitlist_unserved'), 'Waitlisted employees who never got a seat.'),
            ],
            'driver_utilization' => [
                $this->kpi('Drivers', $rows->count(), 'Drivers with at least one completed trip.'),
                $this->kpi('Completed trips', (int) $rows->sum('services'), 'Service runs closed out as completed.'),
                $this->kpi('Service days', (int) $rows->sum('service_days'), 'Distinct travel dates worked, summed per driver.'),
                $this->kpi('Load factor', $this->percentage((int) $rows->sum('boarded'), (int) $rows->sum('seats_offered')), 'Passengers boarded ÷ seats offered for booking.'),
            ],
            'schedule_attendance' => [
                $this->kpi('Runs', $rows->count(), 'Service runs that reached departure in the period.'),
                $this->kpi('Reserved', (int) $rows->sum('reserved'), 'Seats booked across those runs.'),
                $this->kpi('Boarded', (int) $rows->sum('boarded'), 'Employees marked as boarded.'),
                $this->kpi('No-shows', (int) $rows->sum('no_shows'), 'Employees with a reservation who never boarded.'),
                $this->kpi('Attendance rate', $this->percentage((int) $rows->sum('boarded'), (int) $rows->sum('boarded') + (int) $rows->sum('no_shows')), 'Boarded ÷ (boarded + no-shows). Services that did not operate are excluded.'),
                $this->kpi('No-show rate', $this->percentage((int) $rows->sum('no_shows'), (int) $rows->sum('reserved')), 'No-shows ÷ seats reserved.'),
            ],
            'passenger_manifest' => [
                $this->kpi('Passenger records', $rows->count(), 'Attendance rows matching the filters.'),
                $this->kpi('Boarded', $rows->where('status', 'BOARDED')->count(), 'Employees marked as boarded.'),
                $this->kpi('No-shows', $rows->where('status', 'NO_SHOW')->count(), 'Employees with a reservation who never boarded.'),
                $this->kpi('Attendance rate', $this->percentage($rows->where('status', 'BOARDED')->count(), $rows->where('status', '!=', 'SERVICE_NOT_OPERATED')->count()), 'Boarded ÷ (boarded + no-shows). Services that did not operate are excluded.'),
                $this->kpi('Captured by QR', $this->percentage($rows->where('captured_by', 'QR_SCAN')->count(), $rows->where('status', 'BOARDED')->count()), 'Share of boardings recorded by QR scan rather than manually.'),
            ],
            'booking_activity' => [
                $this->kpi('Recorded activities', $rows->count(), 'Booking and waitlist events in the period.'),
                $this->kpi('Bookings', $rows->where('activity', 'RESERVATION_CREATED')->count(), 'New seat reservations.'),
                $this->kpi('Cancellations', $rows->where('activity', 'RESERVATION_CANCELLED')->count(), 'Reservations cancelled before departure.'),
                $this->kpi('Seat changes', $rows->where('activity', 'RESERVATION_SEAT_CHANGED')->count(), 'Employees who moved seat without cancelling.'),
                $this->kpi('Promotions', $rows->where('activity', 'WAITLIST_PROMOTED')->count(), 'Waitlisted employees given a released seat.'),
                $this->kpi('Unserved', $rows->where('activity', 'WAITLIST_UNSERVED')->count(), 'Waitlisted employees who never got a seat.'),
            ],
            'closeout_corrections' => [
                $this->kpi('Amendments', $rows->count(), 'Corrections and reopens applied to finalized services.'),
                $this->kpi('Corrections', $rows->where('action', 'CORRECTION')->count(), 'Finalized records edited in place.'),
                $this->kpi('Reopens', $rows->where('action', 'REOPEN')->count(), 'Finalized services returned to pending closeout.'),
                $this->kpi('Services affected', $rows->pluck('travel_date')->filter()->unique()->count(), 'Distinct service dates touched.'),
                $this->kpi('Administrators', $rows->pluck('corrected_by')->filter()->unique()->count(), 'Distinct administrators who amended a record.'),
            ],
            'access_log' => [
                $this->kpi('Sign-ins', $rows->count(), 'Successful employee portal sign-ins.'),
                $this->kpi('Unique employees', $rows->pluck('employee_id')->unique()->count(), 'Distinct employees who signed in.'),
                $this->kpi('By QR code', $this->percentage($rows->where('login_method', 'QR_SCAN')->count(), $rows->count()), 'Share of sign-ins using the QR credential rather than a typed employee ID.'),
                $this->kpi('Latest sign-in', $this->displayDateTime($rows->max('logged_in_at')), 'Most recent sign-in in the period.'),
            ],
            'incident_log' => [
                $this->kpi('Affected services', $rows->count(), 'Services with a note, an incident, or a not-operated outcome.'),
                $this->kpi('Incidents documented', $rows->filter(fn (object $row): bool => filled($row->incident_notes))->count(), 'Services carrying an explicit incident note.'),
                $this->kpi('Not operated', $rows->where('status', 'NOT_OPERATED')->count(), 'Services that did not run.'),
                $this->kpi('Exception rate', $this->percentage($rows->count(), $this->occurrences([...$filters, 'status' => null])->count()), 'Affected services ÷ all services in the period.'),
            ],
            default => $this->serviceDeliveryKpis($rows),
        };
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{label: string, value: string|int, hint: string}>
     */
    private function serviceDeliveryKpis(Collection $rows): array
    {
        $dueRows = $rows->whereIn('status', ['AWAITING_COMPLETION', 'COMPLETED', 'NOT_OPERATED']);
        $completed = $dueRows->where('status', 'COMPLETED')->count();
        $notOperated = $dueRows->where('status', 'NOT_OPERATED')->count();
        $departed = $dueRows->filter(fn (object $row): bool => filled($row->actual_departure));
        $onTime = $departed->filter(fn (object $row): bool => $this->departureDelay($row) !== null && $this->departureDelay($row) <= $this->onTimeThreshold())->count();

        return [
            $this->kpi('Services due', $dueRows->count(), 'Runs that reached their departure time in the period.'),
            $this->kpi('Completed', $completed, 'Runs closed out as completed.'),
            $this->kpi('Not operated', $notOperated, 'Runs closed out as not operated.'),
            $this->kpi('Pending closeout', $dueRows->where('status', 'AWAITING_COMPLETION')->count(), 'Departed runs still waiting for an administrator to finalize them.'),
            $this->kpi('Operation rate', $this->percentage($completed, $completed + $notOperated), 'Completed ÷ (completed + not operated).'),
            $this->kpi('On-time departures', $this->percentage($onTime, $departed->count()), sprintf('Departed within %d minutes of the scheduled time.', $this->onTimeThreshold())),
        ];
    }

    /** @param array<string, mixed> $filters @return array{label: string, kind: string, data: list<array{label: string, value: int}>} */
    private function chart(array $filters): array
    {
        $report = $filters['report'];

        if (in_array($report, ['vehicle_utilization', 'route_demand', 'driver_utilization'], true)) {
            $key = match ($report) {
                'vehicle_utilization' => 'vehicle',
                'route_demand' => 'route',
                default => 'driver',
            };
            $value = $report === 'route_demand' ? 'reservations' : 'boarded';
            // Always rank by the headline metric, independent of the table's sort.
            $rows = $this->query($filters)->orderByDesc($value)->limit(8)->get();

            return [
                'label' => 'Top '.$rows->count().' by '.($value === 'reservations' ? 'seats reserved' : 'passengers boarded'),
                'kind' => 'ranking',
                'data' => $rows->map(fn (object $row): array => ['label' => (string) $row->{$key}, 'value' => (int) $row->{$value}])->all(),
            ];
        }

        [$query, $dateColumn, $label] = match ($report) {
            'schedule_attendance' => [$this->occurrences($filters)->whereIn('status', ['AWAITING_COMPLETION', 'COMPLETED', 'NOT_OPERATED']), 'travel_date', 'Service runs per day'],
            'passenger_manifest' => [
                $this->attendance([...$filters, 'status' => $filters['status'] ?? 'BOARDED']),
                'o.travel_date',
                $filters['status'] === null ? 'Passengers boarded per day' : Str::headline((string) $filters['status']).' per day',
            ],
            'booking_activity' => [$this->activities($filters), 'occurred_at', 'Booking activity per day'],
            'closeout_corrections' => [$this->corrections($filters), 'c.corrected_at', 'Record amendments per day'],
            'access_log' => [$this->logins($filters), 'logged_in_at', 'Employee sign-ins per day'],
            'incident_log' => [$this->occurrences($filters)->where(fn (Builder $query): Builder => $query->whereNotNull('incident_notes')->orWhereNotNull('operational_notes')->orWhere('status', 'NOT_OPERATED')), 'travel_date', 'Affected services per day'],
            default => [
                $this->occurrences([...$filters, 'status' => $filters['status'] ?? 'COMPLETED']),
                'travel_date',
                ($filters['status'] === null ? 'Completed' : Str::headline((string) $filters['status'])).' services per day',
            ],
        };

        $data = $query->selectRaw("DATE({$dateColumn}) as label, COUNT(*) as value")
            ->groupByRaw("DATE({$dateColumn})")
            ->orderBy('label')
            ->get()
            ->map(fn (object $row): array => ['label' => (string) $row->label, 'value' => (int) $row->value])
            ->all();

        return ['label' => $label, 'kind' => 'timeseries', 'data' => $data];
    }

    /** @param list<array{key: string, label: string, type: string}> $columns @return array<string, mixed> */
    private function paginator(LengthAwarePaginator $paginator, array $columns, array $filters): array
    {
        $keys = array_column($columns, 'key');
        $rows = $this->decorate(collect($paginator->items()), $filters);

        return [
            'data' => $rows->map(fn (object $row): array => collect($keys)
                ->mapWithKeys(fn (string $key): array => [$key => $this->value($row, $key)])
                ->all())->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Attaches values that cannot be produced by the report query itself.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function decorate(Collection $rows, array $filters): Collection
    {
        if ($filters['report'] !== 'driver_utilization') {
            return $rows;
        }

        $durations = $this->driverDurations($filters);
        $rows->each(function (object $row) use ($durations): void {
            $row->recorded_duration = $durations[(int) ($row->driver_id ?? 0)] ?? 0;
        });

        return $rows;
    }

    /** @param array<string, mixed> $filters @return array<int, int> */
    private function driverDurations(array $filters): array
    {
        return $this->operatedOccurrences($filters)
            ->whereNotNull('driver_id')
            ->whereNotNull('actual_departure_at')
            ->whereNotNull('actual_arrival_at')
            ->get(['driver_id', 'actual_departure_at', 'actual_arrival_at'])
            ->groupBy('driver_id')
            ->map(fn ($rows): int => (int) $rows->sum(fn (object $row): int => (int) CarbonImmutable::parse($row->actual_departure_at)->diffInMinutes(CarbonImmutable::parse($row->actual_arrival_at))))
            ->all();
    }

    private function value(object $row, string $key): string|int|float|null
    {
        $value = $row->{$key} ?? null;

        return match ($key) {
            'captured_by', 'login_method' => $value === null ? '—' : (string) $value,
            'departure_delay' => $this->departureDelay($row) ?? '—',
            'load_factor' => $this->percentage((int) ($row->boarded ?? 0), (int) ($row->seats_offered ?? 0)),
            'attendance_rate' => $this->percentage((int) ($row->boarded ?? 0), (int) ($row->boarded ?? 0) + (int) ($row->no_shows ?? 0)),
            'waitlist_conversion' => $this->percentage((int) ($row->waitlist_promoted ?? 0), (int) ($row->waitlist_joined ?? 0)),
            'distance_km', 'avg_distance', 'avg_passengers', 'opening_odometer', 'closing_odometer' => $value === null ? null : number_format((float) $value, 1),
            'recorded_duration' => $this->duration((int) $value),
            'fields_changed' => $this->changedFields($row),
            'cancellation_lead_time' => $this->cancellationLead($row),
            default => $value,
        };
    }

    /** Minutes between the scheduled and actual departure; null when the run never departed. */
    private function departureDelay(object $row): ?int
    {
        $scheduled = $row->scheduled_departure ?? null;
        $actual = $row->actual_departure ?? null;

        if (blank($scheduled) || blank($actual)) {
            return null;
        }

        return (int) CarbonImmutable::parse((string) $scheduled)
            ->diffInMinutes(CarbonImmutable::parse((string) $actual), false);
    }

    /** Human-readable list of the fields an administrator changed during a correction. */
    private function changedFields(object $row): string
    {
        $before = $this->decodeJson($row->before_values ?? null);
        $after = $this->decodeJson($row->after_values ?? null);

        if ($before === [] && $after === []) {
            return '—';
        }

        $changed = collect(array_keys($after + $before))
            ->filter(fn (string $field): bool => ($before[$field] ?? null) !== ($after[$field] ?? null))
            ->map(fn (string $field): string => Str::headline($field))
            ->values();

        return $changed->isEmpty() ? '—' : $changed->implode(', ');
    }

    private function cancellationLead(object $row): string
    {
        $metadata = $this->decodeJson($row->metadata ?? null);
        $leadMinutes = $metadata['cancellation_lead_minutes'] ?? null;

        return is_numeric($leadMinutes) ? $this->duration((int) $leadMinutes) : '—';
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function duration(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $remainingMinutes = $minutes % 60;

        return collect([
            $days > 0 ? "{$days}d" : null,
            $hours > 0 ? "{$hours}h" : null,
            $remainingMinutes > 0 || $minutes === 0 ? "{$remainingMinutes}m" : null,
        ])->filter()->implode(' ');
    }

    private function displayDateTime(mixed $value): string
    {
        return blank($value) ? '—' : CarbonImmutable::parse((string) $value)->format('M j, Y g:i A');
    }

    private function onTimeThreshold(): int
    {
        return max(0, (int) config('shuttle.on_time_threshold_minutes', 5));
    }

    /**
     * @return array{
     *     routes: list<array{id: int, label: string}>,
     *     vehicles: list<array{id: int, label: string}>,
     *     drivers: list<array{id: int, label: string}>,
     *     schedules: list<array{id: int, label: string}>,
     *     departments: list<string>
     * }
     */
    private function filterOptions(): array
    {
        $departments = Department::query()
            ->orderBy('name')
            ->pluck('name')
            ->merge(DB::table('shuttle_service_attendances')->whereNotNull('department')->pluck('department'))
            ->merge(DB::table('employee_login_logs')->whereNotNull('department')->pluck('department'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'routes' => ShuttleRoute::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ShuttleRoute $route): array => ['id' => $route->id, 'label' => $route->name])
                ->all(),
            'vehicles' => Vehicle::query()
                ->orderBy('plate_number')
                ->get(['id', 'plate_number'])
                ->map(fn (Vehicle $vehicle): array => ['id' => $vehicle->id, 'label' => $vehicle->plate_number])
                ->all(),
            'drivers' => Driver::query()
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id'])
                ->map(fn (Driver $driver): array => ['id' => $driver->id, 'label' => $driver->name.' · '.$driver->employee_id])
                ->all(),
            'schedules' => ShuttleSchedule::query()
                ->with('route:id,name')
                ->orderBy('departure_time')
                ->get(['id', 'route_id', 'direction', 'departure_time'])
                ->map(fn (ShuttleSchedule $schedule): array => [
                    'id' => $schedule->id,
                    'label' => sprintf(
                        '%s · %s · %s',
                        $schedule->route?->name ?? 'Unassigned route',
                        mb_substr((string) $schedule->departure_time, 0, 5),
                        Str::headline((string) $schedule->direction),
                    ),
                ])
                ->all(),
            'departments' => $departments,
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalize(array $filters): array
    {
        $now = CarbonImmutable::now((string) config('shuttle.operating_timezone', 'Asia/Manila'));
        $report = (string) ($filters['report'] ?? self::DEFAULT_REPORT);

        if (! ReportCatalog::exists($report)) {
            $report = self::DEFAULT_REPORT;
        }

        return [
            'report' => $report,
            'date_from' => (string) ($filters['date_from'] ?? $now->startOfMonth()->toDateString()),
            'date_to' => (string) ($filters['date_to'] ?? $now->endOfMonth()->toDateString()),
            'route_id' => isset($filters['route_id']) ? (int) $filters['route_id'] : null,
            'vehicle_id' => isset($filters['vehicle_id']) ? (int) $filters['vehicle_id'] : null,
            'driver_id' => isset($filters['driver_id']) ? (int) $filters['driver_id'] : null,
            'schedule_id' => isset($filters['schedule_id']) ? (int) $filters['schedule_id'] : null,
            'department' => filled($filters['department'] ?? null) ? (string) $filters['department'] : null,
            'priority_status' => filled($filters['priority_status'] ?? null) ? (string) $filters['priority_status'] : null,
            'status' => filled($filters['status'] ?? null) ? (string) $filters['status'] : null,
            'sort' => filled($filters['sort'] ?? null) ? (string) $filters['sort'] : null,
            'direction' => filled($filters['direction'] ?? null) ? (string) $filters['direction'] : null,
            'per_page' => (int) ($filters['per_page'] ?? 25),
        ];
    }

    /** @return array{label: string, value: string|int, hint: string} */
    private function kpi(string $label, string|int $value, string $hint = ''): array
    {
        return compact('label', 'value', 'hint');
    }

    /** An undefined rate reads as a dash rather than a misleading 0.0%. */
    private function percentage(int $numerator, int $denominator): string
    {
        return $denominator === 0
            ? '—'
            : number_format($numerator / $denominator * 100, 1).'%';
    }

    /** @param array<string, mixed> $filters */
    private function period(array $filters): string
    {
        return CarbonImmutable::parse($filters['date_from'])->format('M j, Y').' – '.CarbonImmutable::parse($filters['date_to'])->format('M j, Y');
    }
}
