<?php

namespace App\Services\Reports;

use Illuminate\Support\Str;

/**
 * The single source of truth for the admin reporting suite: which reports exist,
 * what question each one answers, how its columns render, and which filters apply.
 * Both the report catalogue page and the individual report pages read from here.
 */
class ReportCatalog
{
    public const DEFAULT_REPORT = 'service_delivery';

    /** Column render hints consumed by the report table. */
    public const TYPE_TEXT = 'text';

    public const TYPE_NUMERIC = 'numeric';

    public const TYPE_DECIMAL = 'decimal';

    public const TYPE_PERCENT = 'percent';

    public const TYPE_DATE = 'date';

    public const TYPE_TIME = 'time';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_STATUS = 'status';

    public const TYPE_ATTENDANCE = 'attendance';

    public const TYPE_BADGE = 'badge';

    public const TYPE_NOTES = 'notes';

    /** @var array<string, array{label: string, description: string}> */
    private const CATEGORIES = [
        'operations' => [
            'label' => 'Operations',
            'description' => 'What ran, how punctually, and what went wrong.',
        ],
        'ridership' => [
            'label' => 'Ridership',
            'description' => 'Who booked, who boarded, and where demand is concentrated.',
        ],
        'assets' => [
            'label' => 'Fleet & drivers',
            'description' => 'How hard vehicles and drivers are working.',
        ],
        'audit' => [
            'label' => 'Audit & access',
            'description' => 'Booking trails, record amendments, and portal sign-ins.',
        ],
    ];

    /**
     * Retired slugs kept alive so bookmarks and older deep links keep resolving.
     *
     * @var array<string, string>
     */
    private const LEGACY_SLUGS = [
        'service-completion' => 'service-delivery',
        'fleet-utilization' => 'vehicle-utilization',
        'route-schedule-demand' => 'route-demand',
        'shuttle-attendance' => 'passenger-manifest',
        'reservation-waitlist-activity' => 'booking-activity',
        'login-activity' => 'access-log',
    ];

    /**
     * @var array<string, array{
     *     slug: string,
     *     category: string,
     *     title: string,
     *     answers: string,
     *     description: string,
     *     filters: list<string>,
     *     statuses: list<string>,
     *     default_sort: array{0: string, 1: string},
     *     columns: list<array{0: string, 1: string, 2?: string}>
     * }>
     */
    private const REPORTS = [
        'service_delivery' => [
            'slug' => 'service-delivery',
            'category' => 'operations',
            'title' => 'Service delivery register',
            'answers' => 'Did every planned trip run, depart on time, and get closed out?',
            'description' => 'One row per service run with punctuality, ridership, odometer and closeout detail.',
            'filters' => ['schedule', 'route', 'vehicle', 'driver', 'status'],
            'statuses' => ['SCHEDULED', 'AWAITING_COMPLETION', 'COMPLETED', 'NOT_OPERATED'],
            'default_sort' => ['scheduled_departure', 'desc'],
            'columns' => [
                ['service_date', 'Service date', self::TYPE_DATE],
                ['route', 'Route'],
                ['direction', 'Direction', self::TYPE_BADGE],
                ['vehicle', 'Vehicle'],
                ['driver', 'Driver'],
                ['scheduled_departure', 'Scheduled departure', self::TYPE_DATETIME],
                ['actual_departure', 'Actual departure', self::TYPE_DATETIME],
                ['departure_delay', 'Delay (min)', self::TYPE_NUMERIC],
                ['reserved', 'Reserved', self::TYPE_NUMERIC],
                ['boarded', 'Boarded', self::TYPE_NUMERIC],
                ['no_shows', 'No-shows', self::TYPE_NUMERIC],
                ['distance_km', 'Distance (km)', self::TYPE_DECIMAL],
                ['opening_odometer', 'Opening odometer', self::TYPE_DECIMAL],
                ['closing_odometer', 'Closing odometer', self::TYPE_DECIMAL],
                ['status', 'Status', self::TYPE_STATUS],
                ['finalizer', 'Closed out by'],
            ],
        ],
        'incident_log' => [
            'slug' => 'incident-log',
            'category' => 'operations',
            'title' => 'Incident & exception log',
            'answers' => 'Which services had incidents or failed to operate, and why?',
            'description' => 'Every service carrying an operational note, an incident note, or a not-operated outcome.',
            'filters' => ['schedule', 'route', 'vehicle', 'driver', 'status'],
            'statuses' => ['SCHEDULED', 'AWAITING_COMPLETION', 'COMPLETED', 'NOT_OPERATED'],
            'default_sort' => ['service_date', 'desc'],
            'columns' => [
                ['service_date', 'Service date', self::TYPE_DATE],
                ['route', 'Route'],
                ['direction', 'Direction', self::TYPE_BADGE],
                ['vehicle', 'Vehicle'],
                ['driver', 'Driver'],
                ['status', 'Outcome', self::TYPE_STATUS],
                ['operational_notes', 'Operational notes', self::TYPE_NOTES],
                ['incident_notes', 'Incident notes', self::TYPE_NOTES],
                ['not_operated_reason', 'Not-operated reason', self::TYPE_NOTES],
            ],
        ],
        'schedule_attendance' => [
            'slug' => 'schedule-attendance',
            'category' => 'ridership',
            'title' => 'Schedule attendance summary',
            'answers' => 'For each schedule run, how many employees booked, boarded, and never showed up?',
            'description' => 'Attendance rolled up per service run so you can compare schedules day by day.',
            'filters' => ['schedule', 'route', 'vehicle', 'driver', 'status'],
            'statuses' => ['AWAITING_COMPLETION', 'COMPLETED', 'NOT_OPERATED'],
            'default_sort' => ['travel_date', 'desc'],
            'columns' => [
                ['travel_date', 'Travel date', self::TYPE_DATE],
                ['route', 'Route'],
                ['direction', 'Direction', self::TYPE_BADGE],
                ['departure_time', 'Departure', self::TYPE_TIME],
                ['vehicle', 'Vehicle'],
                ['driver', 'Driver'],
                ['seats_offered', 'Seats offered', self::TYPE_NUMERIC],
                ['reserved', 'Reserved', self::TYPE_NUMERIC],
                ['boarded', 'Boarded', self::TYPE_NUMERIC],
                ['no_shows', 'No-shows', self::TYPE_NUMERIC],
                ['attendance_rate', 'Attendance rate', self::TYPE_PERCENT],
                ['load_factor', 'Load factor', self::TYPE_PERCENT],
                ['waitlist_unserved', 'Unserved waitlist', self::TYPE_NUMERIC],
                ['status', 'Status', self::TYPE_STATUS],
            ],
        ],
        'passenger_manifest' => [
            'slug' => 'passenger-manifest',
            'category' => 'ridership',
            'title' => 'Passenger manifest',
            'answers' => 'Who was booked on a service, and did they actually board?',
            'description' => 'Passenger-level attendance with seat, department, priority tier, and how attendance was captured.',
            'filters' => ['schedule', 'route', 'vehicle', 'driver', 'status', 'department', 'priority'],
            'statuses' => ['BOARDED', 'NO_SHOW', 'SERVICE_NOT_OPERATED'],
            'default_sort' => ['service_date', 'desc'],
            'columns' => [
                ['service_date', 'Service date', self::TYPE_DATE],
                ['route', 'Route'],
                ['direction', 'Direction', self::TYPE_BADGE],
                ['departure_time', 'Departure', self::TYPE_TIME],
                ['employee_id', 'Employee ID'],
                ['employee', 'Passenger'],
                ['department', 'Department'],
                ['priority', 'Priority tier', self::TYPE_BADGE],
                ['seat', 'Seat', self::TYPE_NUMERIC],
                ['status', 'Attendance', self::TYPE_ATTENDANCE],
                ['captured_by', 'Captured by', self::TYPE_BADGE],
                ['recorded_at', 'Recorded at', self::TYPE_DATETIME],
                ['recorded_by', 'Recorded by'],
            ],
        ],
        'route_demand' => [
            'slug' => 'route-demand',
            'category' => 'ridership',
            'title' => 'Route & departure demand',
            'answers' => 'Which routes and departure times are busiest or turning employees away?',
            'description' => 'Demand per route, direction and departure time, including waitlist pressure.',
            'filters' => ['route', 'vehicle', 'driver'],
            'statuses' => [],
            'default_sort' => ['reservations', 'desc'],
            'columns' => [
                ['route', 'Route'],
                ['direction', 'Direction', self::TYPE_BADGE],
                ['departure_time', 'Departure', self::TYPE_TIME],
                ['services', 'Runs', self::TYPE_NUMERIC],
                ['reservations', 'Reserved', self::TYPE_NUMERIC],
                ['boarded', 'Boarded', self::TYPE_NUMERIC],
                ['no_shows', 'No-shows', self::TYPE_NUMERIC],
                ['load_factor', 'Load factor', self::TYPE_PERCENT],
                ['cancellations', 'Cancellations', self::TYPE_NUMERIC],
                ['waitlist_joined', 'Waitlist joins', self::TYPE_NUMERIC],
                ['waitlist_promoted', 'Promoted', self::TYPE_NUMERIC],
                ['waitlist_unserved', 'Unserved', self::TYPE_NUMERIC],
                ['waitlist_conversion', 'Waitlist conversion', self::TYPE_PERCENT],
            ],
        ],
        'vehicle_utilization' => [
            'slug' => 'vehicle-utilization',
            'category' => 'assets',
            'title' => 'Vehicle utilization',
            'answers' => 'How hard is each vehicle working, and how full does it run?',
            'description' => 'Completed deployment, passenger load, seats offered and distance per vehicle.',
            'filters' => ['route', 'vehicle', 'driver'],
            'statuses' => [],
            'default_sort' => ['services', 'desc'],
            'columns' => [
                ['vehicle', 'Vehicle'],
                ['vehicle_type', 'Type'],
                ['service_days', 'Service days', self::TYPE_NUMERIC],
                ['services', 'Completed trips', self::TYPE_NUMERIC],
                ['seats_offered', 'Seats offered', self::TYPE_NUMERIC],
                ['boarded', 'Passengers', self::TYPE_NUMERIC],
                ['load_factor', 'Load factor', self::TYPE_PERCENT],
                ['avg_passengers', 'Avg passengers / trip', self::TYPE_DECIMAL],
                ['distance_km', 'Distance (km)', self::TYPE_DECIMAL],
                ['avg_distance', 'Avg distance (km)', self::TYPE_DECIMAL],
            ],
        ],
        'driver_utilization' => [
            'slug' => 'driver-utilization',
            'category' => 'assets',
            'title' => 'Driver utilization',
            'answers' => 'How many trips, days and driving hours is each driver covering?',
            'description' => 'Completed trips, active service days, passenger load and recorded running time per driver.',
            'filters' => ['route', 'vehicle', 'driver'],
            'statuses' => [],
            'default_sort' => ['services', 'desc'],
            'columns' => [
                ['driver', 'Driver'],
                ['employee_id', 'Employee ID'],
                ['service_days', 'Service days', self::TYPE_NUMERIC],
                ['services', 'Completed trips', self::TYPE_NUMERIC],
                ['seats_offered', 'Seats offered', self::TYPE_NUMERIC],
                ['boarded', 'Passengers', self::TYPE_NUMERIC],
                ['load_factor', 'Load factor', self::TYPE_PERCENT],
                ['distance_km', 'Distance (km)', self::TYPE_DECIMAL],
                ['recorded_duration', 'Recorded driving time'],
            ],
        ],
        'booking_activity' => [
            'slug' => 'booking-activity',
            'category' => 'audit',
            'title' => 'Booking & waitlist activity',
            'answers' => 'What did employees book, change, cancel, or queue for?',
            'description' => 'Chronological audit of reservations, seat changes, cancellations and waitlist movement.',
            'filters' => ['route', 'vehicle', 'driver', 'status', 'priority'],
            'statuses' => [
                'RESERVATION_CREATED',
                'RESERVATION_CANCELLED',
                'RESERVATION_SEAT_CHANGED',
                'WAITLIST_JOINED',
                'WAITLIST_WITHDRAWN',
                'WAITLIST_PROMOTED',
                'WAITLIST_UNSERVED',
            ],
            'default_sort' => ['occurred_at', 'desc'],
            'columns' => [
                ['occurred_at', 'Occurred at', self::TYPE_DATETIME],
                ['activity', 'Activity', self::TYPE_BADGE],
                ['employee_id', 'Employee ID'],
                ['employee', 'Employee'],
                ['travel_date', 'Travel date', self::TYPE_DATE],
                ['route', 'Route'],
                ['direction', 'Direction', self::TYPE_BADGE],
                ['departure_time', 'Departure', self::TYPE_TIME],
                ['vehicle', 'Vehicle'],
                ['seat', 'Seat', self::TYPE_NUMERIC],
                ['priority', 'Priority', self::TYPE_BADGE],
                ['cancellation_lead_time', 'Cancelled ahead of departure'],
            ],
        ],
        'closeout_corrections' => [
            'slug' => 'closeout-corrections',
            'category' => 'audit',
            'title' => 'Closeout correction audit',
            'answers' => 'Which finalized service records were amended or reopened, by whom, and why?',
            'description' => 'Every correction and reopen applied to a closed service, with the fields that changed.',
            'filters' => ['route', 'vehicle', 'status'],
            'statuses' => ['CORRECTION', 'REOPEN'],
            'default_sort' => ['corrected_at', 'desc'],
            'columns' => [
                ['corrected_at', 'Corrected at', self::TYPE_DATETIME],
                ['action', 'Action', self::TYPE_BADGE],
                ['travel_date', 'Service date', self::TYPE_DATE],
                ['route', 'Route'],
                ['vehicle', 'Vehicle'],
                ['service_status', 'Service status', self::TYPE_STATUS],
                ['fields_changed', 'Fields changed', self::TYPE_NOTES],
                ['reason', 'Reason', self::TYPE_NOTES],
                ['corrected_by', 'Corrected by'],
            ],
        ],
        'access_log' => [
            'slug' => 'access-log',
            'category' => 'audit',
            'title' => 'Employee access log',
            'answers' => 'Who signed in to the employee portal, when, and by QR or employee ID?',
            'description' => 'Successful employee portal sign-ins with the credential used.',
            'filters' => ['department', 'priority'],
            'statuses' => [],
            'default_sort' => ['logged_in_at', 'desc'],
            'columns' => [
                ['logged_in_at', 'Signed in at', self::TYPE_DATETIME],
                ['employee_id', 'Employee ID'],
                ['employee', 'Employee'],
                ['department', 'Department'],
                ['priority', 'Priority', self::TYPE_BADGE],
                ['login_method', 'Signed in with', self::TYPE_BADGE],
            ],
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::REPORTS);
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_column(self::REPORTS, 'slug');
    }

    /** @return list<string> */
    public static function legacySlugs(): array
    {
        return array_keys(self::LEGACY_SLUGS);
    }

    /**
     * Slugs the router should accept — current ones plus retired ones we still redirect.
     *
     * @return list<string>
     */
    public static function routableSlugs(): array
    {
        return array_values(array_unique([...self::slugs(), ...self::legacySlugs()]));
    }

    public static function slugReplacing(string $legacySlug): ?string
    {
        return self::LEGACY_SLUGS[$legacySlug] ?? null;
    }

    /** Resolves current and retired slugs alike, so a stale bookmark still validates. */
    public static function keyFromSlug(string $slug): ?string
    {
        $slug = self::LEGACY_SLUGS[$slug] ?? $slug;

        foreach (self::REPORTS as $key => $report) {
            if ($report['slug'] === $slug) {
                return $key;
            }
        }

        return null;
    }

    public static function slugFromKey(string $key): string
    {
        return self::REPORTS[$key]['slug'] ?? self::REPORTS[self::DEFAULT_REPORT]['slug'];
    }

    public static function defaultSlug(): string
    {
        return self::slugFromKey(self::DEFAULT_REPORT);
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::REPORTS);
    }

    /**
     * Full definition for one report, with columns expanded into render-ready shape.
     *
     * @return array<string, mixed>
     */
    public static function definition(string $key): array
    {
        $report = self::REPORTS[$key] ?? self::REPORTS[self::DEFAULT_REPORT];

        return [
            'key' => $key,
            'slug' => $report['slug'],
            'url' => '/admin/reports/'.$report['slug'],
            'category' => $report['category'],
            'category_label' => self::CATEGORIES[$report['category']]['label'],
            'title' => $report['title'],
            'answers' => $report['answers'],
            'description' => $report['description'],
            'filters' => $report['filters'],
            'columns' => self::columns($key),
            'default_sort' => [
                'key' => $report['default_sort'][0],
                'direction' => $report['default_sort'][1],
            ],
        ];
    }

    /** @return list<array{key: string, label: string, type: string}> */
    public static function columns(string $key): array
    {
        $report = self::REPORTS[$key] ?? self::REPORTS[self::DEFAULT_REPORT];

        return array_map(fn (array $column): array => [
            'key' => $column[0],
            'label' => $column[1],
            'type' => $column[2] ?? self::TYPE_TEXT,
        ], $report['columns']);
    }

    /** @return list<string> */
    public static function columnKeys(string $key): array
    {
        return array_column(self::columns($key), 'key');
    }

    /** @return list<string> */
    public static function filtersFor(string $key): array
    {
        return (self::REPORTS[$key] ?? self::REPORTS[self::DEFAULT_REPORT])['filters'];
    }

    /** @return list<array{value: string, label: string}> */
    public static function statusOptions(string $key): array
    {
        $statuses = (self::REPORTS[$key] ?? self::REPORTS[self::DEFAULT_REPORT])['statuses'];

        return array_map(fn (string $status): array => [
            'value' => $status,
            'label' => Str::headline($status),
        ], $statuses);
    }

    /** @return array{key: string, direction: string} */
    public static function defaultSort(string $key): array
    {
        $sort = (self::REPORTS[$key] ?? self::REPORTS[self::DEFAULT_REPORT])['default_sort'];

        return ['key' => $sort[0], 'direction' => $sort[1]];
    }

    /**
     * The catalogue shown on the reports landing page, grouped by category.
     *
     * @return list<array{key: string, label: string, description: string, reports: list<array<string, mixed>>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::CATEGORIES as $categoryKey => $category) {
            $reports = [];

            foreach (self::REPORTS as $reportKey => $report) {
                if ($report['category'] !== $categoryKey) {
                    continue;
                }

                $reports[] = [
                    'key' => $reportKey,
                    'slug' => $report['slug'],
                    'url' => '/admin/reports/'.$report['slug'],
                    'title' => $report['title'],
                    'answers' => $report['answers'],
                    'description' => $report['description'],
                    'columns' => array_slice(array_column(self::columns($reportKey), 'label'), 0, 5),
                ];
            }

            $groups[] = [
                'key' => $categoryKey,
                'label' => $category['label'],
                'description' => $category['description'],
                'reports' => $reports,
            ];
        }

        return $groups;
    }

    /**
     * Flat list used by the in-page report switcher.
     *
     * @return list<array{key: string, slug: string, url: string, title: string, category_label: string}>
     */
    public static function switcher(): array
    {
        $reports = [];

        foreach (self::REPORTS as $key => $report) {
            $reports[] = [
                'key' => $key,
                'slug' => $report['slug'],
                'url' => '/admin/reports/'.$report['slug'],
                'title' => $report['title'],
                'category_label' => self::CATEGORIES[$report['category']]['label'],
            ];
        }

        return $reports;
    }
}
