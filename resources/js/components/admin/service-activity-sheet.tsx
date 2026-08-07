import type { ServiceLifecycleStatus } from '@/components/admin/service-operation-types';
import { ServiceStatusBadge } from '@/components/admin/service-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { CalendarClock, CalendarDays, History, LoaderCircle, RefreshCw, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type ActivityTab = 'TODAY' | 'UPCOMING' | 'PAST';

/** Which schedule participant the sheet is describing. Drives the secondary columns. */
export type ActivitySubject = 'VEHICLE' | 'ROUTE' | 'DRIVER';

interface RunBase {
    travel_date: string;
    route_name: string | null;
    origin: string | null;
    destination: string | null;
    direction: string | null;
    departure_time: string;
    plate_number: string | null;
    driver_name: string | null;
    reservation_count: number;
    boarded_count: number;
}

interface PastRun extends RunBase {
    id: number;
    status: ServiceLifecycleStatus;
    no_show_count: number;
    distance_km: string | number | null;
    not_operated_reason: string | null;
}

interface PlannedRun extends RunBase {
    key: string;
    occurrence_id: number | null;
    schedule_id: number | null;
    scheduled_departure_at: string;
    status: ServiceLifecycleStatus | null;
    effective_capacity: number;
    has_departed: boolean;
}

export interface ServiceActivity {
    subject: {
        id: number;
        label: string;
        sublabel: string;
        status: string;
    };
    summary: {
        completed_services: number;
        not_operated_services: number;
        passengers_boarded: number;
        active_schedules: number;
        today_count: number;
        upcoming_count: number;
    };
    past: PastRun[];
    today: PlannedRun[];
    upcoming: PlannedRun[];
    today_date: string;
    history_limit: number;
    upcoming_days: number;
}

const subjectNouns: Record<ActivitySubject, string> = {
    VEHICLE: 'vehicle',
    ROUTE: 'route',
    DRIVER: 'driver',
};

function displayDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function displayTime(time: string | null): string {
    if (!time) {
        return '—';
    }

    const [hourValue, minute = '00'] = time.split(':');
    const hour = Number(hourValue);

    return `${hour % 12 || 12}:${minute} ${hour >= 12 ? 'PM' : 'AM'}`;
}

function routeLabel(entry: RunBase): string {
    const [origin, destination] = entry.direction === 'RETURN' ? [entry.destination, entry.origin] : [entry.origin, entry.destination];

    if (!origin || !destination) {
        return entry.route_name ?? 'Unassigned route';
    }

    return `${origin} → ${destination}`;
}

/** The subject itself is already in the header, so it is not repeated per row. */
function assignmentLabel(run: RunBase, subject: ActivitySubject): string {
    if (subject === 'VEHICLE') {
        return run.driver_name ?? 'Unassigned driver';
    }

    if (subject === 'DRIVER') {
        return run.plate_number ?? 'No vehicle';
    }

    return [run.plate_number, run.driver_name].filter(Boolean).join(' · ') || 'Unassigned';
}

function assignmentHeading(subject: ActivitySubject): string {
    if (subject === 'VEHICLE') {
        return 'Driver';
    }

    if (subject === 'DRIVER') {
        return 'Vehicle';
    }

    return 'Vehicle & driver';
}

function StatTile({ label, value, hint }: { label: string; value: string | number; hint?: string }) {
    return (
        <div className="bg-muted/40 rounded-xl border p-3">
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
            {hint && <p className="text-muted-foreground truncate text-xs">{hint}</p>}
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return <p className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">{message}</p>;
}

function PlannedRunTable({ runs, subject, showDate }: { runs: PlannedRun[]; subject: ActivitySubject; showDate: boolean }) {
    return (
        <div className="overflow-x-auto rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{showDate ? 'Departure' : 'Time'}</TableHead>
                        <TableHead>Route</TableHead>
                        <TableHead>{assignmentHeading(subject)}</TableHead>
                        <TableHead>Seats booked</TableHead>
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {runs.map((run) => (
                        <TableRow key={run.key}>
                            <TableCell className="whitespace-nowrap">
                                {showDate && <span className="block font-medium">{displayDate(run.travel_date)}</span>}
                                <span className={showDate ? 'text-muted-foreground block text-xs' : 'font-medium'}>
                                    {displayTime(run.departure_time)}
                                </span>
                            </TableCell>
                            <TableCell>
                                <span className="block truncate">{routeLabel(run)}</span>
                                <span className="text-muted-foreground block text-xs">{run.direction === 'RETURN' ? 'Return' : 'Outbound'}</span>
                            </TableCell>
                            <TableCell className="text-muted-foreground truncate">{assignmentLabel(run, subject)}</TableCell>
                            <TableCell className="tabular-nums">
                                {run.reservation_count}
                                {run.effective_capacity > 0 && <span className="text-muted-foreground"> / {run.effective_capacity}</span>}
                            </TableCell>
                            <TableCell>
                                {run.status ? (
                                    <ServiceStatusBadge status={run.status} />
                                ) : (
                                    <Badge variant="outline">{run.has_departed ? 'Departed' : 'Planned'}</Badge>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

export function ServiceActivitySheet({
    subject,
    endpoint,
    subjectId,
    subjectLabel,
    open,
    onOpenChange,
}: {
    subject: ActivitySubject;
    /** Collection path, e.g. `/admin/vehicles`. */
    endpoint: string;
    subjectId: number | null;
    subjectLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [tab, setTab] = useState<ActivityTab>('TODAY');
    const [activity, setActivity] = useState<ServiceActivity | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const requestId = useRef(0);
    const noun = subjectNouns[subject];

    const load = useCallback(
        async (id: number): Promise<void> => {
            const currentRequest = ++requestId.current;
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(`${endpoint}/${id}/activity`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error(response.status === 404 ? `This ${noun} no longer exists.` : `The ${noun} activity could not be retrieved.`);
                }

                const payload = (await response.json()) as ServiceActivity;

                if (currentRequest === requestId.current) {
                    setActivity(payload);
                }
            } catch (caught) {
                if (currentRequest === requestId.current) {
                    setError(caught instanceof Error ? caught.message : `The ${noun} activity could not be retrieved.`);
                }
            } finally {
                if (currentRequest === requestId.current) {
                    setLoading(false);
                }
            }
        },
        [endpoint, noun],
    );

    useEffect(() => {
        if (!open || subjectId === null) {
            return;
        }

        setTab('TODAY');
        setActivity(null);
        void load(subjectId);
    }, [load, open, subjectId]);

    const summary = activity?.summary;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex h-full w-full flex-col gap-0 overflow-y-auto p-0 sm:max-w-3xl">
                <SheetHeader className="border-b px-5 py-5 pr-14 sm:px-6">
                    <SheetTitle className="capitalize">{noun} activity</SheetTitle>
                    <SheetDescription>{activity ? `${activity.subject.label} · ${activity.subject.sublabel}` : subjectLabel}</SheetDescription>
                </SheetHeader>

                <div className="flex-1 space-y-5 px-5 py-5 sm:px-6">
                    {loading && (
                        <p className="text-muted-foreground flex items-center justify-center gap-2 py-16 text-sm">
                            <LoaderCircle className="size-4 animate-spin" />
                            Loading activity...
                        </p>
                    )}

                    {!loading && error && (
                        <div className="space-y-4 rounded-xl border border-dashed p-8 text-center">
                            <TriangleAlert className="text-muted-foreground mx-auto size-6" />
                            <p className="text-sm font-medium">{error}</p>
                            {subjectId !== null && (
                                <Button type="button" variant="outline" size="sm" onClick={() => void load(subjectId)}>
                                    <RefreshCw />
                                    Try again
                                </Button>
                            )}
                        </div>
                    )}

                    {!loading && !error && activity && summary && (
                        <>
                            {activity.subject.status !== 'ACTIVE' && (
                                <p className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/35 dark:text-amber-100">
                                    This {noun} is {activity.subject.status.toLowerCase()}. Planned runs below stay listed until their schedules are
                                    reassigned or deactivated.
                                </p>
                            )}

                            <div className="grid gap-3 sm:grid-cols-3">
                                <StatTile
                                    label="Completed services"
                                    value={summary.completed_services}
                                    hint={`${summary.not_operated_services} not operated`}
                                />
                                <StatTile label="Passengers boarded" value={summary.passengers_boarded} hint="Across all recorded services" />
                                <StatTile
                                    label="Assigned schedules"
                                    value={summary.active_schedules}
                                    hint={`${summary.today_count} today · ${summary.upcoming_count} upcoming`}
                                />
                            </div>

                            <ToggleGroup
                                type="single"
                                value={tab}
                                onValueChange={(value) => value && setTab(value as ActivityTab)}
                                variant="outline"
                                aria-label="Activity section"
                                className="w-full rounded-md border p-0.5"
                            >
                                <ToggleGroupItem value="TODAY" className="flex-1 gap-2 px-3">
                                    <CalendarDays className="size-4" />
                                    Today
                                </ToggleGroupItem>
                                <ToggleGroupItem value="UPCOMING" className="flex-1 gap-2 px-3">
                                    <CalendarClock className="size-4" />
                                    Upcoming
                                </ToggleGroupItem>
                                <ToggleGroupItem value="PAST" className="flex-1 gap-2 px-3">
                                    <History className="size-4" />
                                    Past
                                </ToggleGroupItem>
                            </ToggleGroup>

                            {tab === 'TODAY' &&
                                (activity.today.length === 0 ? (
                                    <EmptyState message={`No runs are scheduled for this ${noun} on ${displayDate(activity.today_date)}.`} />
                                ) : (
                                    <PlannedRunTable runs={activity.today} subject={subject} showDate={false} />
                                ))}

                            {tab === 'UPCOMING' &&
                                (activity.upcoming.length === 0 ? (
                                    <EmptyState message={`No runs are scheduled in the next ${activity.upcoming_days} days.`} />
                                ) : (
                                    <PlannedRunTable runs={activity.upcoming} subject={subject} showDate />
                                ))}

                            {tab === 'PAST' &&
                                (activity.past.length === 0 ? (
                                    <EmptyState message={`This ${noun} has no recorded service history yet.`} />
                                ) : (
                                    <div className="overflow-x-auto rounded-xl border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Departure</TableHead>
                                                    <TableHead>Route</TableHead>
                                                    <TableHead>{assignmentHeading(subject)}</TableHead>
                                                    <TableHead>Boarded</TableHead>
                                                    <TableHead>Distance</TableHead>
                                                    <TableHead>Status</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {activity.past.map((run) => (
                                                    <TableRow key={run.id}>
                                                        <TableCell className="whitespace-nowrap">
                                                            <span className="block font-medium">{displayDate(run.travel_date)}</span>
                                                            <span className="text-muted-foreground block text-xs">
                                                                {displayTime(run.departure_time)}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell>
                                                            <span className="block truncate">{routeLabel(run)}</span>
                                                            <span className="text-muted-foreground block text-xs">
                                                                {run.direction === 'RETURN' ? 'Return' : 'Outbound'}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground truncate">
                                                            {assignmentLabel(run, subject)}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {run.boarded_count}
                                                            <span className="text-muted-foreground"> / {run.reservation_count}</span>
                                                            {run.no_show_count > 0 && (
                                                                <span className="text-muted-foreground block text-xs">
                                                                    {run.no_show_count} no-show{run.no_show_count === 1 ? '' : 's'}
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">
                                                            {run.distance_km === null ? '—' : `${Number(run.distance_km).toFixed(1)} km`}
                                                        </TableCell>
                                                        <TableCell>
                                                            <ServiceStatusBadge status={run.status} />
                                                            {run.not_operated_reason && (
                                                                <span className="text-muted-foreground mt-1 block max-w-40 truncate text-xs">
                                                                    {run.not_operated_reason}
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                ))}

                            <p className="text-muted-foreground text-xs">
                                Past services show the {activity.history_limit} most recent records. Upcoming runs are projected from each schedule's
                                operating days for the next {activity.upcoming_days} days.
                            </p>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
