import type { AttendanceStatus, ServiceLifecycleStatus } from '@/components/admin/service-operation-types';
import { AttendanceStatusBadge, ServiceStatusBadge } from '@/components/admin/service-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { CalendarClock, LoaderCircle, QrCode, RefreshCw, ScanLine, TicketCheck, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type ActivityTab = 'LOGINS' | 'BOARDINGS' | 'RESERVATIONS';

interface LoginEntry {
    id: number;
    logged_in_at: string | null;
    method: 'QR_SCAN' | 'EMPLOYEE_CODE' | null;
    method_label: string;
    department: string | null;
    priority_status: string;
}

interface BoardingEntry {
    id: number;
    travel_date: string | null;
    route_name: string | null;
    origin: string | null;
    destination: string | null;
    direction: string | null;
    departure_time: string | null;
    plate_number: string | null;
    driver_name: string | null;
    service_status: ServiceLifecycleStatus | null;
    seat_number: number;
    status: AttendanceStatus;
    recording_method: 'QR_SCAN' | 'MANUAL' | 'FINALIZATION';
    boarded_at: string | null;
}

interface ReservationEntry {
    id: number;
    travel_date: string;
    route_name: string | null;
    origin: string | null;
    destination: string | null;
    direction: string | null;
    departure_time: string;
    scheduled_departure_at: string;
    plate_number: string | null;
    driver_name: string | null;
    seat_number: number;
    source: string;
    reserved_at: string | null;
    service_status: ServiceLifecycleStatus | null;
}

export interface EmployeeActivity {
    employee: {
        employee_id: number;
        employee_code: string;
        name: string;
        email: string;
        department: string | null;
        position: string | null;
        priority_status: 'REGULAR' | 'PRIORITY';
        status: 'ACTIVE' | 'INACTIVE';
    };
    summary: {
        total_logins: number;
        qr_logins: number;
        last_login_at: string | null;
        boarded_count: number;
        no_show_count: number;
        upcoming_count: number;
    };
    logins: LoginEntry[];
    boardings: BoardingEntry[];
    reservations: ReservationEntry[];
    history_limit: number;
}

const recordingMethodLabels: Record<BoardingEntry['recording_method'], string> = {
    QR_SCAN: 'QR scan',
    MANUAL: 'Manual',
    FINALIZATION: 'Closeout',
};

function displayDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

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

function routeLabel(entry: { route_name: string | null; origin: string | null; destination: string | null; direction: string | null }): string {
    const [origin, destination] = entry.direction === 'RETURN' ? [entry.destination, entry.origin] : [entry.origin, entry.destination];

    if (!origin || !destination) {
        return entry.route_name ?? 'Unassigned route';
    }

    return `${origin} → ${destination}`;
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

export function EmployeeActivitySheet({
    employeeId,
    employeeName,
    open,
    onOpenChange,
}: {
    employeeId: number | null;
    employeeName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [tab, setTab] = useState<ActivityTab>('LOGINS');
    const [activity, setActivity] = useState<EmployeeActivity | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const requestId = useRef(0);

    const load = useCallback(async (id: number): Promise<void> => {
        const currentRequest = ++requestId.current;
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`/admin/employees/${id}/activity`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                throw new Error(response.status === 404 ? 'This employee record no longer exists.' : 'The activity trail could not be retrieved.');
            }

            const payload = (await response.json()) as EmployeeActivity;

            if (currentRequest === requestId.current) {
                setActivity(payload);
            }
        } catch (caught) {
            if (currentRequest === requestId.current) {
                setError(caught instanceof Error ? caught.message : 'The activity trail could not be retrieved.');
            }
        } finally {
            if (currentRequest === requestId.current) {
                setLoading(false);
            }
        }
    }, []);

    useEffect(() => {
        if (!open || employeeId === null) {
            return;
        }

        setTab('LOGINS');
        setActivity(null);
        void load(employeeId);
    }, [employeeId, load, open]);

    const summary = activity?.summary;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex h-full w-full flex-col gap-0 overflow-y-auto p-0 sm:max-w-3xl">
                <SheetHeader className="border-b px-5 py-5 pr-14 sm:px-6">
                    <SheetTitle>Employee activity</SheetTitle>
                    <SheetDescription>{activity ? `${activity.employee.name} · ${activity.employee.employee_code}` : employeeName}</SheetDescription>
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
                            {employeeId !== null && (
                                <Button type="button" variant="outline" size="sm" onClick={() => void load(employeeId)}>
                                    <RefreshCw />
                                    Try again
                                </Button>
                            )}
                        </div>
                    )}

                    {!loading && !error && activity && summary && (
                        <>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <StatTile label="Sign-ins" value={summary.total_logins} hint={`${summary.qr_logins} by QR code`} />
                                <StatTile
                                    label="Services boarded"
                                    value={summary.boarded_count}
                                    hint={`${summary.no_show_count} no-show${summary.no_show_count === 1 ? '' : 's'}`}
                                />
                                <StatTile
                                    label="Upcoming reservations"
                                    value={summary.upcoming_count}
                                    hint={summary.last_login_at ? `Last seen ${displayDateTime(summary.last_login_at)}` : 'Never signed in'}
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
                                <ToggleGroupItem value="LOGINS" className="flex-1 gap-2 px-3">
                                    <ScanLine className="size-4" />
                                    Login activity
                                </ToggleGroupItem>
                                <ToggleGroupItem value="BOARDINGS" className="flex-1 gap-2 px-3">
                                    <CalendarClock className="size-4" />
                                    Past services
                                </ToggleGroupItem>
                                <ToggleGroupItem value="RESERVATIONS" className="flex-1 gap-2 px-3">
                                    <TicketCheck className="size-4" />
                                    Reserved
                                </ToggleGroupItem>
                            </ToggleGroup>

                            {tab === 'LOGINS' &&
                                (activity.logins.length === 0 ? (
                                    <EmptyState message="This employee has not signed in yet." />
                                ) : (
                                    <div className="overflow-x-auto rounded-xl border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Signed in</TableHead>
                                                    <TableHead>Method</TableHead>
                                                    <TableHead>Department</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {activity.logins.map((login) => (
                                                    <TableRow key={login.id}>
                                                        <TableCell className="whitespace-nowrap">{displayDateTime(login.logged_in_at)}</TableCell>
                                                        <TableCell>
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    'gap-1',
                                                                    login.method === 'QR_SCAN' &&
                                                                        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
                                                                )}
                                                            >
                                                                {login.method === 'QR_SCAN' && <QrCode className="size-3" />}
                                                                {login.method_label}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground">{login.department ?? '—'}</TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                ))}

                            {tab === 'BOARDINGS' &&
                                (activity.boardings.length === 0 ? (
                                    <EmptyState message="This employee has not been marked on a finalized service yet." />
                                ) : (
                                    <div className="overflow-x-auto rounded-xl border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Travel date</TableHead>
                                                    <TableHead>Route</TableHead>
                                                    <TableHead>Seat</TableHead>
                                                    <TableHead>Attendance</TableHead>
                                                    <TableHead>Recorded</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {activity.boardings.map((boarding) => (
                                                    <TableRow key={boarding.id}>
                                                        <TableCell className="whitespace-nowrap">
                                                            <span className="font-medium">{displayDate(boarding.travel_date)}</span>
                                                            <span className="text-muted-foreground block text-xs">
                                                                {displayTime(boarding.departure_time)} · {boarding.plate_number ?? '—'}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell>
                                                            <span className="block truncate">{routeLabel(boarding)}</span>
                                                            {boarding.service_status && (
                                                                <ServiceStatusBadge status={boarding.service_status} className="mt-1" />
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">{boarding.seat_number}</TableCell>
                                                        <TableCell>
                                                            <AttendanceStatusBadge status={boarding.status} />
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground text-xs">
                                                            {recordingMethodLabels[boarding.recording_method]}
                                                            {boarding.boarded_at && (
                                                                <span className="block">{displayDateTime(boarding.boarded_at)}</span>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                ))}

                            {tab === 'RESERVATIONS' &&
                                (activity.reservations.length === 0 ? (
                                    <EmptyState message="This employee has no upcoming reservations." />
                                ) : (
                                    <div className="overflow-x-auto rounded-xl border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Travel date</TableHead>
                                                    <TableHead>Route</TableHead>
                                                    <TableHead>Seat</TableHead>
                                                    <TableHead>Reserved</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {activity.reservations.map((reservation) => (
                                                    <TableRow key={reservation.id}>
                                                        <TableCell className="whitespace-nowrap">
                                                            <span className="font-medium">{displayDate(reservation.travel_date)}</span>
                                                            <span className="text-muted-foreground block text-xs">
                                                                {displayTime(reservation.departure_time)} · {reservation.plate_number ?? '—'}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell>
                                                            <span className="block truncate">{routeLabel(reservation)}</span>
                                                            <span className="text-muted-foreground block text-xs">
                                                                {reservation.driver_name ?? 'Unassigned driver'}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="tabular-nums">{reservation.seat_number}</TableCell>
                                                        <TableCell className="text-muted-foreground text-xs">
                                                            {reservation.source === 'AUTO_ASSIGNED' ? 'Auto-assigned' : 'Self-selected'}
                                                            <span className="block">{displayDateTime(reservation.reserved_at)}</span>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                ))}

                            <p className="text-muted-foreground text-xs">
                                Login and service history show the {activity.history_limit} most recent records.
                            </p>
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
