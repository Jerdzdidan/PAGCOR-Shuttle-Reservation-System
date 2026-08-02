<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Driver;
use App\Models\ShuttleRoute;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminReportService
{
    public const DEFAULT_REPORT = 'service_completion';

    /** @var array<string, string> */
    private const REPORT_SLUGS = [
        'service-completion' => 'service_completion',
        'fleet-utilization' => 'fleet_utilization',
        'route-schedule-demand' => 'route_schedule_demand',
        'shuttle-attendance' => 'shuttle_attendance',
        'driver-utilization' => 'driver_utilization',
        'reservation-waitlist-activity' => 'reservation_waitlist_activity',
        'login-activity' => 'login_activity',
        'incident-log' => 'incident_log',
    ];

    /** @return list<string> */
    public static function reportKeys(): array
    {
        return array_values(self::REPORT_SLUGS);
    }

    /** @return list<string> */
    public static function reportSlugs(): array
    {
        return array_keys(self::REPORT_SLUGS);
    }

    public static function reportKeyFromSlug(string $reportSlug): ?string
    {
        return self::REPORT_SLUGS[$reportSlug] ?? null;
    }

    public static function reportSlugFromKey(string $reportKey): ?string
    {
        $reportSlug = array_search($reportKey, self::REPORT_SLUGS, true);

        return $reportSlug === false ? null : $reportSlug;
    }

    public static function defaultReportSlug(): string
    {
        return self::reportSlugFromKey(self::DEFAULT_REPORT) ?? 'service-completion';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(array $filters): array
    {
        $filters = $this->normalize($filters);
        $definition = $this->definition($filters['report']);
        $paginator = $this->query($filters)->paginate($filters['per_page'])->withQueryString();

        return [
            'title' => $definition['title'],
            'description' => $definition['description'],
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'statusOptions' => $this->statusOptions($filters['report']),
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
        $definition = $this->definition($filters['report']);
        $keys = array_column($definition['columns'], 'key');

        $rows = $this->query($filters)->get();
        if ($filters['report'] === 'driver_utilization') {
            $durations = $this->driverDurations($filters);
            $rows->each(function (object $row) use ($durations): void {
                $row->recorded_duration = $durations[(int) $row->driver_id] ?? 0;
            });
        }

        return [
            'headings' => array_column($definition['columns'], 'label'),
            'rows' => $rows->map(fn (object $row): array => array_map(fn (string $key): string|int|float|null => $this->value($row, $key), $keys))->all(),
            'title' => $definition['title'],
            'period' => $this->period($filters),
        ];
    }

    /** @return array{title: string, description: string, columns: list<array{key: string, label: string}>} */
    private function definition(string $report): array
    {
        return match ($report) {
            'fleet_utilization' => $this->definitionFor('Fleet utilization report', 'Compare completed vehicle deployment, passenger load, capacity, and distance traveled.', [['vehicle', 'Vehicle'], ['vehicle_type', 'Type'], ['service_days', 'Active service days'], ['services', 'Completed trips'], ['distance_km', 'Total distance (km)'], ['offered_capacity', 'Offered capacity'], ['boarded', 'Passengers'], ['occupancy', 'Occupancy'], ['avg_passengers', 'Avg passengers / trip'], ['avg_distance', 'Avg distance (km)']]),
            'route_schedule_demand' => $this->definitionFor('Route and schedule demand report', 'Identify the route, direction, and departure times with the highest demand.', [['route', 'Route'], ['direction', 'Direction'], ['departure_time', 'Departure time'], ['services', 'Services'], ['reservations', 'Reservations'], ['boarded', 'Boarded'], ['no_shows', 'No-shows'], ['cancellations', 'Cancellations'], ['waitlist_joined', 'Waitlist joins'], ['waitlist_promoted', 'Promotions'], ['waitlist_unserved', 'Unserved'], ['waitlist_conversion', 'Waitlist conversion']]),
            'shuttle_attendance' => $this->definitionFor('Shuttle attendance report', 'Passenger manifest with attendance outcomes, department, and priority tier.', [['service_date', 'Service date'], ['route', 'Route'], ['employee_id', 'Employee ID'], ['employee', 'Passenger'], ['department', 'Department'], ['priority', 'Priority tier'], ['seat', 'Seat'], ['status', 'Attendance status'], ['recorded_at', 'Recorded at'], ['recorded_by', 'Recorded by']]),
            'driver_utilization' => $this->definitionFor('Driver utilization report', 'Review active service days, occupancy, distance, and recorded running time.', [['driver', 'Driver'], ['employee_id', 'Employee ID'], ['service_days', 'Service days'], ['services', 'Services'], ['completed', 'Completed'], ['boarded', 'Passengers'], ['occupancy', 'Occupancy'], ['distance_km', 'Distance (km)'], ['recorded_duration', 'Recorded duration']]),
            'reservation_waitlist_activity' => $this->definitionFor('Reservation and waitlist activity report', 'Audit reservations, cancellations, withdrawals, promotions, and unserved waitlist entries.', [['occurred_at', 'Occurred at'], ['activity', 'Activity'], ['employee_id', 'Employee ID'], ['employee', 'Employee'], ['schedule_id', 'Schedule ID'], ['travel_date', 'Travel date'], ['route', 'Route'], ['direction', 'Direction'], ['departure_time', 'Departure time'], ['vehicle', 'Vehicle'], ['driver', 'Driver'], ['seat', 'Seat'], ['priority', 'Priority'], ['cancellation_lead_time', 'Cancellation lead time']]),
            'login_activity' => $this->definitionFor('Login activity report', 'Audit successful employee logins, including each employee’s latest login.', [['logged_in_at', 'Logged in at'], ['employee_id', 'Employee ID'], ['employee', 'Employee'], ['department', 'Department'], ['priority', 'Priority']]),
            'incident_log' => $this->definitionFor('Incident log report', 'Review operational notes, incidents, and their final service outcome.', [['service_date', 'Service date'], ['route', 'Route'], ['vehicle', 'Vehicle'], ['driver', 'Driver'], ['status', 'Outcome'], ['operational_notes', 'Operational notes'], ['incident_notes', 'Incident notes'], ['not_operated_reason', 'Not-operated reason']]),
            default => $this->definitionFor('Service Completion Register', 'Detailed closeout, operational, odometer, and finalization records.', [['service_date', 'Service date'], ['route', 'Route'], ['vehicle', 'Vehicle'], ['driver', 'Driver'], ['scheduled_departure', 'Scheduled departure'], ['actual_departure', 'Actual departure'], ['actual_arrival', 'Actual arrival'], ['opening_odometer', 'Opening odometer'], ['closing_odometer', 'Closing odometer'], ['distance_km', 'Distance (km)'], ['status', 'Status'], ['operational_notes', 'Operational notes'], ['incident_notes', 'Incident notes'], ['not_operated_reason', 'Not-operated reason'], ['finalizer', 'Finalized by']]),
        };
    }

    /** @param list<array{0: string, 1: string}> $columns @return array{title: string, description: string, columns: list<array{key: string, label: string}>} */
    private function definitionFor(string $title, string $description, array $columns): array
    {
        return ['title' => $title, 'description' => $description, 'columns' => array_map(fn (array $column): array => ['key' => $column[0], 'label' => $column[1]], $columns)];
    }

    /** @param array<string, mixed> $filters */
    private function query(array $filters): Builder
    {
        return match ($filters['report']) {
            'fleet_utilization' => $this->operatedOccurrences($filters)
                ->selectRaw("COALESCE(plate_number, 'Unassigned') as vehicle, COALESCE(vehicle_type, '—') as vehicle_type, COUNT(DISTINCT travel_date) as service_days, COUNT(*) as services, SUM(COALESCE(distance_km, 0)) as distance_km, SUM(available_capacity) as offered_capacity, SUM(boarded_count) as boarded, AVG(boarded_count) as avg_passengers, AVG(distance_km) as avg_distance")
                ->groupBy('plate_number', 'vehicle_type')->orderByDesc('services'),
            'route_schedule_demand' => $this->operatedOccurrences($filters)
                ->leftJoinSub($this->activityCounts($filters), 'activity_counts', 'activity_counts.occurrence_id', '=', 'shuttle_service_occurrences.id')
                ->selectRaw("COALESCE(route_name, 'Unassigned route') as route, direction, departure_time, COUNT(*) as services, SUM(reservation_count) as reservations, SUM(boarded_count) as boarded, SUM(no_show_count) as no_shows, SUM(COALESCE(activity_counts.cancellations, 0)) as cancellations, SUM(COALESCE(activity_counts.waitlist_joined, 0)) as waitlist_joined, SUM(COALESCE(activity_counts.waitlist_promoted, 0)) as waitlist_promoted, SUM(unserved_waitlist_count) as waitlist_unserved")
                ->groupBy('route_name', 'direction', 'departure_time')->orderByDesc('reservations'),
            'driver_utilization' => $this->operatedOccurrences($filters)
                ->selectRaw("driver_id, COALESCE(driver_name, 'Unassigned') as driver, COALESCE(driver_employee_id, '—') as employee_id, COUNT(DISTINCT travel_date) as service_days, COUNT(*) as services, SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed, SUM(boarded_count) as boarded, SUM(available_capacity) as capacity, SUM(COALESCE(distance_km, 0)) as distance_km")
                ->groupBy('driver_id', 'driver_name', 'driver_employee_id')->orderByDesc('services'),
            'shuttle_attendance' => $this->attendance($filters)->selectRaw("o.travel_date as service_date, COALESCE(o.route_name, 'Unassigned route') as route, COALESCE(a.employee_code_snapshot, a.employee_id_snapshot) as employee_id, a.employee_name as employee, COALESCE(a.department, '—') as department, a.priority_status as priority, a.seat_number as seat, a.status, COALESCE(a.boarded_at, a.created_at) as recorded_at, COALESCE(a.recorded_by_name, attendance_recorders.name, '—') as recorded_by")->orderByDesc('recorded_at'),
            'reservation_waitlist_activity' => $this->activities($filters)->selectRaw("occurred_at, REPLACE(event_type, '_', ' ') as activity, COALESCE(employee_code_snapshot, employee_id_snapshot, '—') as employee_id, COALESCE(employee_name, '—') as employee, COALESCE(shuttle_schedule_id_snapshot, '—') as schedule_id, COALESCE(travel_date, '—') as travel_date, COALESCE(route_name, 'Unassigned route') as route, COALESCE(direction, '—') as direction, COALESCE(departure_time, '—') as departure_time, COALESCE(plate_number, '—') as vehicle, COALESCE(driver_name, '—') as driver, COALESCE(seat_number, '—') as seat, COALESCE(employee_priority_status, '—') as priority, metadata")->orderByDesc('occurred_at'),
            'login_activity' => $this->logins($filters)->selectRaw("logged_in_at, COALESCE(employee_code_snapshot, employee_id_snapshot) as employee_id, employee_name as employee, COALESCE(department, '—') as department, priority_status as priority")->orderByDesc('logged_in_at'),
            'incident_log' => $this->occurrences($filters)->where(fn (Builder $query): Builder => $query->whereNotNull('incident_notes')->orWhereNotNull('operational_notes')->orWhere('status', 'NOT_OPERATED'))
                ->selectRaw("travel_date as service_date, COALESCE(route_name, 'Unassigned route') as route, COALESCE(plate_number, '—') as vehicle, COALESCE(driver_name, 'Unassigned') as driver, status, operational_notes, incident_notes, not_operated_reason")->orderByDesc('scheduled_departure_at'),
            default => $this->occurrences($filters)->leftJoin('users as finalizers', 'finalizers.id', '=', 'shuttle_service_occurrences.finalized_by')
                ->selectRaw("travel_date as service_date, COALESCE(route_name, 'Unassigned route') as route, COALESCE(plate_number, '—') as vehicle, COALESCE(driver_name, 'Unassigned') as driver, scheduled_departure_at as scheduled_departure, actual_departure_at as actual_departure, actual_arrival_at as actual_arrival, opening_odometer_km as opening_odometer, closing_odometer_km as closing_odometer, distance_km, status, operational_notes, incident_notes, not_operated_reason, COALESCE(shuttle_service_occurrences.finalized_by_name, finalizers.name, '—') as finalizer")->orderByDesc('scheduled_departure_at'),
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

    /** @param array<string, mixed> $filters @return list<array{label: string, value: string|int}> */
    private function kpis(array $filters): array
    {
        $rows = $this->query($filters)->get();

        return match ($filters['report']) {
            'fleet_utilization' => [$this->kpi('Active service days', (int) $rows->sum('service_days')), $this->kpi('Completed trips', (int) $rows->sum('services')), $this->kpi('Distance', number_format((float) $rows->sum('distance_km'), 1).' km'), $this->kpi('Offered capacity', (int) $rows->sum('offered_capacity')), $this->kpi('Occupancy', $this->percentage((int) $rows->sum('boarded'), (int) $rows->sum('offered_capacity')))],
            'route_schedule_demand' => [$this->kpi('Services', (int) $rows->sum('services')), $this->kpi('Reservations', (int) $rows->sum('reservations')), $this->kpi('Cancellations', (int) $rows->sum('cancellations')), $this->kpi('No-shows', (int) $rows->sum('no_shows')), $this->kpi('Waitlist joins', (int) $rows->sum('waitlist_joined')), $this->kpi('Promotions', (int) $rows->sum('waitlist_promoted')), $this->kpi('Unserved', (int) $rows->sum('waitlist_unserved'))],
            'driver_utilization' => [$this->kpi('Driver service days', (int) $rows->sum('service_days')), $this->kpi('Services', (int) $rows->sum('services')), $this->kpi('Completed', (int) $rows->sum('completed')), $this->kpi('Occupancy', $this->percentage((int) $rows->sum('boarded'), (int) $rows->sum('capacity')))],
            'shuttle_attendance' => [$this->kpi('Attendance records', $rows->count()), $this->kpi('Boarded', $rows->where('status', 'BOARDED')->count()), $this->kpi('No-shows', $rows->where('status', 'NO_SHOW')->count()), $this->kpi('Attendance rate', $this->percentage($rows->where('status', 'BOARDED')->count(), $rows->where('status', '!=', 'SERVICE_NOT_OPERATED')->count()))],
            'reservation_waitlist_activity' => [$this->kpi('Recorded activities', $rows->count()), $this->kpi('Cancellations', $rows->filter(fn (object $row): bool => $row->activity === 'RESERVATION CANCELLED')->count()), $this->kpi('Withdrawals', $rows->filter(fn (object $row): bool => $row->activity === 'WAITLIST WITHDRAWN')->count()), $this->kpi('Promotions', $rows->filter(fn (object $row): bool => $row->activity === 'WAITLIST PROMOTED')->count()), $this->kpi('Unserved', $rows->filter(fn (object $row): bool => $row->activity === 'WAITLIST UNSERVED')->count())],
            'login_activity' => [$this->kpi('Successful logins', $rows->count()), $this->kpi('Unique employees', $rows->pluck('employee_id')->unique()->count()), $this->kpi('Priority employee logins', $rows->where('priority', 'PRIORITY')->count()), $this->kpi('Latest login', $rows->max('logged_in_at') ?? '—')],
            'incident_log' => [$this->kpi('Affected services', $rows->count()), $this->kpi('Documented notes', $rows->filter(fn (object $row): bool => filled($row->operational_notes) || filled($row->incident_notes) || filled($row->not_operated_reason))->count()), $this->kpi('Not operated', $rows->where('status', 'NOT_OPERATED')->count()), $this->kpi('Incident rate', $this->percentage($rows->count(), $this->occurrences($filters)->count()))],
            'service_completion' => $this->serviceCompletionKpis($rows),
            default => [$this->kpi('Groups', $rows->count()), $this->kpi('Services', (int) $rows->sum('services')), $this->kpi('Completed', (int) $rows->sum('completed')), $this->kpi('Passengers boarded', (int) $rows->sum('boarded'))],
        };
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{label: string, value: string|int}>
     */
    private function serviceCompletionKpis(Collection $rows): array
    {
        $dueRows = $rows->whereIn('status', [
            'AWAITING_COMPLETION',
            'COMPLETED',
            'NOT_OPERATED',
        ]);
        $completed = $dueRows->where('status', 'COMPLETED')->count();
        $notOperated = $dueRows->where('status', 'NOT_OPERATED')->count();
        $finalized = $completed + $notOperated;

        return [
            $this->kpi('Due services', $dueRows->count()),
            $this->kpi(
                'Pending closeout',
                $dueRows->where('status', 'AWAITING_COMPLETION')->count()
            ),
            $this->kpi('Completed', $completed),
            $this->kpi('Not operated', $notOperated),
            $this->kpi(
                'Closeout rate',
                $this->percentage($finalized, $dueRows->count())
            ),
            $this->kpi(
                'Operation rate',
                $this->percentage($completed, $dueRows->count())
            ),
        ];
    }

    /** @param array<string, mixed> $filters @return array{label: string, data: list<array{label: string, value: int}>} */
    private function chart(array $filters): array
    {
        $report = $filters['report'];
        if (in_array($report, ['fleet_utilization', 'route_schedule_demand', 'driver_utilization'], true)) {
            $rows = $this->query($filters)->limit(8)->get();
            $key = $report === 'fleet_utilization' ? 'vehicle' : ($report === 'route_schedule_demand' ? 'route' : 'driver');
            $value = $report === 'route_schedule_demand' ? 'reservations' : ($report === 'driver_utilization' ? 'completed' : 'boarded');

            return ['label' => Str::headline(str_replace('_', ' ', $value)), 'data' => $rows->map(fn (object $row): array => ['label' => (string) $row->{$key}, 'value' => (int) $row->{$value}])->all()];
        }
        [$query, $dateColumn, $label] = match ($report) {
            'shuttle_attendance' => [
                $this->attendance([
                    ...$filters,
                    'status' => $filters['status'] ?? 'BOARDED',
                ]),
                'o.travel_date',
                $filters['status'] === null
                    ? 'Boarded passengers'
                    : Str::headline((string) $filters['status']),
            ],
            'reservation_waitlist_activity' => [$this->activities($filters), 'occurred_at', 'Recorded activities'],
            'login_activity' => [$this->logins($filters), 'logged_in_at', 'QR logins'],
            'incident_log' => [$this->occurrences($filters)->where(fn (Builder $query): Builder => $query->whereNotNull('incident_notes')->orWhereNotNull('operational_notes')->orWhere('status', 'NOT_OPERATED')), 'travel_date', 'Affected services'],
            default => [
                $this->occurrences([
                    ...$filters,
                    'status' => $filters['status'] ?? 'COMPLETED',
                ]),
                'travel_date',
                ($filters['status'] === null
                    ? 'Completed'
                    : Str::headline((string) $filters['status'])).' services',
            ],
        };
        $data = $query->selectRaw("DATE({$dateColumn}) as label, COUNT(*) as value")->groupByRaw("DATE({$dateColumn})")->orderBy('label')->get()->map(fn (object $row): array => ['label' => (string) $row->label, 'value' => (int) $row->value])->all();

        return compact('label', 'data');
    }

    /** @param list<array{key: string, label: string}> $columns @return array<string, mixed> */
    private function paginator(LengthAwarePaginator $paginator, array $columns, array $filters): array
    {
        $keys = array_column($columns, 'key');
        $durations = $filters['report'] === 'driver_utilization' ? $this->driverDurations($filters) : [];

        return ['data' => $paginator->getCollection()->map(function (object $row) use ($keys, $durations): array {
            if (isset($row->driver_id)) {
                $row->recorded_duration = $durations[(int) $row->driver_id] ?? 0;
            }

            return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $this->value($row, $key)])->all();
        })->all(), 'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()];
    }

    /** @param array<string, mixed> $filters @return array<int, int> */
    private function driverDurations(array $filters): array
    {
        return $this->operatedOccurrences($filters)->whereNotNull('driver_id')->whereNotNull('actual_departure_at')->whereNotNull('actual_arrival_at')->get(['driver_id', 'actual_departure_at', 'actual_arrival_at'])->groupBy('driver_id')->map(fn ($rows): int => $rows->sum(fn (object $row): int => CarbonImmutable::parse($row->actual_departure_at)->diffInMinutes(CarbonImmutable::parse($row->actual_arrival_at))))->all();
    }

    private function value(object $row, string $key): string|int|float|null
    {
        $value = $row->{$key} ?? null;
        if (in_array($key, ['status', 'activity', 'direction', 'priority'], true) && is_string($value)) {
            return Str::headline($value);
        }
        if (in_array($key, ['load_factor', 'occupancy'], true)) {
            return $this->percentage((int) ($row->boarded ?? 0), (int) ($row->capacity ?? $row->offered_capacity ?? 0));
        }
        if ($key === 'waitlist_conversion') {
            return $this->percentage((int) ($row->waitlist_promoted ?? 0), (int) ($row->waitlist_joined ?? 0));
        }
        if (in_array($key, ['distance_km', 'avg_distance', 'avg_passengers'], true)) {
            return number_format((float) $value, 1);
        }
        if ($key === 'recorded_duration') {
            return ((int) $value).' min';
        }
        if ($key === 'cancellation_lead_time') {
            $metadata = is_string($row->metadata ?? null)
                ? json_decode($row->metadata, true)
                : null;
            $leadMinutes = is_array($metadata)
                ? $metadata['cancellation_lead_minutes'] ?? null
                : null;

            return ! is_numeric($leadMinutes)
                ? '—'
                : $this->duration((int) $leadMinutes);
        }

        return $value;
    }

    private function duration(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $remainingMinutes = $minutes % 60;

        return collect([
            $days > 0 ? "{$days}d" : null,
            $hours > 0 ? "{$hours}h" : null,
            $remainingMinutes > 0 || $minutes === 0
                ? "{$remainingMinutes}m"
                : null,
        ])->filter()->implode(' ');
    }

    /**
     * @return array{
     *     routes: list<array{id: int, label: string}>,
     *     vehicles: list<array{id: int, label: string}>,
     *     drivers: list<array{id: int, label: string}>,
     *     departments: list<string>
     * }
     */
    private function filterOptions(): array
    {
        $departments = Department::query()
            ->orderBy('name')
            ->pluck('name')
            ->merge(
                DB::table('shuttle_service_attendances')
                    ->whereNotNull('department')
                    ->pluck('department')
            )
            ->merge(
                DB::table('employee_login_logs')
                    ->whereNotNull('department')
                    ->pluck('department')
            )
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'routes' => ShuttleRoute::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ShuttleRoute $route): array => [
                    'id' => $route->id,
                    'label' => $route->name,
                ])
                ->all(),
            'vehicles' => Vehicle::query()
                ->orderBy('plate_number')
                ->get(['id', 'plate_number'])
                ->map(fn (Vehicle $vehicle): array => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->plate_number,
                ])
                ->all(),
            'drivers' => Driver::query()
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id'])
                ->map(fn (Driver $driver): array => [
                    'id' => $driver->id,
                    'label' => $driver->name.' · '.$driver->employee_id,
                ])
                ->all(),
            'departments' => $departments,
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function statusOptions(string $report): array
    {
        $values = match ($report) {
            'shuttle_attendance' => ['BOARDED', 'NO_SHOW', 'SERVICE_NOT_OPERATED'],
            'reservation_waitlist_activity' => ['RESERVATION_CREATED', 'RESERVATION_CANCELLED', 'WAITLIST_JOINED', 'WAITLIST_WITHDRAWN', 'WAITLIST_PROMOTED', 'WAITLIST_UNSERVED'],
            'fleet_utilization', 'route_schedule_demand', 'driver_utilization', 'login_activity' => [],
            default => ['SCHEDULED', 'AWAITING_COMPLETION', 'COMPLETED', 'NOT_OPERATED'],
        };

        return array_map(fn (string $value): array => ['value' => $value, 'label' => Str::headline($value)], $values);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalize(array $filters): array
    {
        $now = CarbonImmutable::now((string) config('shuttle.operating_timezone', 'Asia/Manila'));

        return ['report' => (string) ($filters['report'] ?? self::DEFAULT_REPORT), 'date_from' => (string) ($filters['date_from'] ?? $now->startOfMonth()->toDateString()), 'date_to' => (string) ($filters['date_to'] ?? $now->endOfMonth()->toDateString()), 'route_id' => isset($filters['route_id']) ? (int) $filters['route_id'] : null, 'vehicle_id' => isset($filters['vehicle_id']) ? (int) $filters['vehicle_id'] : null, 'driver_id' => isset($filters['driver_id']) ? (int) $filters['driver_id'] : null, 'department' => filled($filters['department'] ?? null) ? (string) $filters['department'] : null, 'priority_status' => filled($filters['priority_status'] ?? null) ? (string) $filters['priority_status'] : null, 'status' => filled($filters['status'] ?? null) ? (string) $filters['status'] : null, 'per_page' => (int) ($filters['per_page'] ?? 25)];
    }

    /** @return array{label: string, value: string|int} */
    private function kpi(string $label, string|int $value): array
    {
        return compact('label', 'value');
    }

    private function percentage(int $numerator, int $denominator): string
    {
        return number_format($denominator ? $numerator / $denominator * 100 : 0, 1).'%';
    }

    /** @param array<string, mixed> $filters */
    private function days(array $filters): int
    {
        return CarbonImmutable::parse($filters['date_from'])->diffInDays(CarbonImmutable::parse($filters['date_to'])) + 1;
    }

    /** @param array<string, mixed> $filters */
    private function period(array $filters): string
    {
        return CarbonImmutable::parse($filters['date_from'])->format('M j, Y').' – '.CarbonImmutable::parse($filters['date_to'])->format('M j, Y');
    }
}
