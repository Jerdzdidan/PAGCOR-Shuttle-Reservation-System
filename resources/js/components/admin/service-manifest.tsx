import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router } from '@inertiajs/react';
import { CheckCheck, CircleAlert, LoaderCircle, Search, ShieldCheck, Undo2, UserCheck, UsersRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { QrAttendanceScanner } from './qr-attendance-scanner';
import {
    manifestEmployeeDepartment,
    manifestEmployeeIdentifier,
    manifestEmployeeName,
    manifestEmployeePriority,
    manifestEntries,
    manifestStatus,
    type ServiceOccurrence,
} from './service-operation-types';
import { ServiceSeatMap } from './service-seat-map';
import { AttendanceStatusBadge } from './service-status-badge';

interface ServiceManifestProps {
    occurrence: ServiceOccurrence;
    onRefresh: () => void;
}

function reservationId(entry: ReturnType<typeof manifestEntries>[number]): number | null {
    return entry.reservation_id ?? entry.shuttle_reservation_id ?? entry.reservation?.id ?? entry.id ?? null;
}

function formatBoardedAt(value?: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function recordingMethodLabel(method?: string | null): string {
    if (!method) {
        return '—';
    }

    return method
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export function ServiceManifest({ occurrence, onRefresh }: ServiceManifestProps) {
    const [search, setSearch] = useState('');
    const [updatingReservationId, setUpdatingReservationId] = useState<number | null>(null);
    const [markingAll, setMarkingAll] = useState(false);
    const entries = manifestEntries(occurrence);
    const editable = occurrence.status === 'SCHEDULED' || occurrence.status === 'AWAITING_COMPLETION';
    const boardedCount = entries.filter((entry) => manifestStatus(entry) === 'BOARDED').length;
    const pendingCount = entries.filter((entry) => manifestStatus(entry) === 'PENDING').length;
    const visibleEntries = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return entries;
        }

        return entries.filter((entry) =>
            [manifestEmployeeName(entry), manifestEmployeeDepartment(entry) ?? '', manifestEmployeeIdentifier(entry), String(entry.seat_number)]
                .join(' ')
                .toLowerCase()
                .includes(query),
        );
    }, [entries, search]);

    function updateAttendance(entry: (typeof entries)[number], status: 'BOARDED' | 'UNMARKED'): void {
        const id = reservationId(entry);

        if (!id) {
            toast.error('This manifest row is missing its reservation reference.');
            return;
        }

        router.patch(
            `/admin/finished-services/${occurrence.id}/attendance/${id}`,
            { status },
            {
                preserveScroll: true,
                onStart: () => setUpdatingReservationId(id),
                onSuccess: () => {
                    toast.success(status === 'BOARDED' ? 'Passenger marked as boarded.' : 'Passenger returned to unmarked.');
                    onRefresh();
                },
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    toast.error(typeof message === 'string' ? message : 'Attendance could not be updated.');
                },
                onFinish: () => setUpdatingReservationId(null),
            },
        );
    }

    function markAllBoarded(): void {
        router.post(
            `/admin/finished-services/${occurrence.id}/attendance/mark-all`,
            {},
            {
                preserveScroll: true,
                onStart: () => setMarkingAll(true),
                onSuccess: () => {
                    toast.success('All reserved passengers were marked as boarded.');
                    onRefresh();
                },
                onError: (errors) => {
                    const message = Object.values(errors)[0];
                    toast.error(typeof message === 'string' ? message : 'The manifest could not be updated.');
                },
                onFinish: () => setMarkingAll(false),
            },
        );
    }

    return (
        <div className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-3">
                <Card className="shadow-none">
                    <CardContent className="flex items-center gap-3 p-4">
                        <span className="bg-primary/10 text-primary rounded-xl p-2.5">
                            <UsersRound className="size-5" />
                        </span>
                        <div>
                            <p className="text-muted-foreground text-xs">Reserved</p>
                            <p className="text-xl font-semibold">{entries.length}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card className="border-emerald-200 shadow-none dark:border-emerald-900">
                    <CardContent className="flex items-center gap-3 p-4">
                        <span className="rounded-xl bg-emerald-100 p-2.5 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            <UserCheck className="size-5" />
                        </span>
                        <div>
                            <p className="text-muted-foreground text-xs">Boarded</p>
                            <p className="text-xl font-semibold">{boardedCount}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card className="border-amber-200 shadow-none dark:border-amber-900">
                    <CardContent className="flex items-center gap-3 p-4">
                        <span className="rounded-xl bg-amber-100 p-2.5 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            <CircleAlert className="size-5" />
                        </span>
                        <div>
                            <p className="text-muted-foreground text-xs">Unmarked</p>
                            <p className="text-xl font-semibold">{pendingCount}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card className="overflow-hidden shadow-none">
                <CardHeader className="border-b bg-slate-50/70 dark:bg-slate-950/35">
                    <CardTitle className="text-base">Shuttle seat map</CardTitle>
                </CardHeader>
                <CardContent className="p-4 sm:p-5">
                    <ServiceSeatMap occurrence={occurrence} />
                </CardContent>
            </Card>

            {editable && (
                <Card className="overflow-hidden shadow-none">
                    <CardHeader className="border-b bg-slate-50/70 dark:bg-slate-950/35">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ScanLineIcon />
                            Record passenger boarding
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4 sm:p-5">
                        <QrAttendanceScanner occurrenceId={occurrence.id} onRecorded={onRefresh} />
                    </CardContent>
                </Card>
            )}

            <Card className="overflow-hidden shadow-none">
                <CardHeader className="gap-3 border-b bg-slate-50/70 sm:flex-row sm:items-center sm:justify-between dark:bg-slate-950/35">
                    <div>
                        <CardTitle className="text-base">Reserved passenger manifest</CardTitle>
                        <p className="text-muted-foreground mt-1 text-xs">Only employees with a reservation for this service can be boarded.</p>
                    </div>
                    {editable && entries.length > 0 && (
                        <Button type="button" variant="outline" size="sm" disabled={markingAll || pendingCount === 0} onClick={markAllBoarded}>
                            {markingAll ? <LoaderCircle className="animate-spin" /> : <CheckCheck />}
                            Mark all boarded
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4 p-0">
                    {entries.length > 0 && (
                        <div className="relative px-4 pt-4 sm:px-5">
                            <Search className="text-muted-foreground pointer-events-none absolute top-6.5 left-7 size-4" />
                            <Input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Find employee, department, or seat…"
                                className="pl-9"
                            />
                        </div>
                    )}

                    {entries.length === 0 ? (
                        <div className="px-5 py-12 text-center">
                            <UsersRound className="text-muted-foreground/50 mx-auto size-8" />
                            <p className="mt-3 font-medium">No reserved passengers</p>
                            <p className="text-muted-foreground mt-1 text-sm">This empty service still requires an operational closeout.</p>
                        </div>
                    ) : visibleEntries.length === 0 ? (
                        <div className="px-5 py-10 text-center">
                            <p className="font-medium">No manifest rows match your search.</p>
                        </div>
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto lg:block">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Seat</TableHead>
                                            <TableHead>Employee</TableHead>
                                            <TableHead>Department</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Recorded</TableHead>
                                            <TableHead className="text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {visibleEntries.map((entry) => {
                                            const status = manifestStatus(entry);
                                            const id = reservationId(entry);
                                            const isUpdating = id !== null && updatingReservationId === id;

                                            return (
                                                <TableRow key={`${manifestEmployeeIdentifier(entry)}-${entry.seat_number}`}>
                                                    <TableCell className="font-semibold">#{entry.seat_number}</TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <span>{manifestEmployeeName(entry)}</span>
                                                            {manifestEmployeePriority(entry) === 'PRIORITY' && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950"
                                                                >
                                                                    <ShieldCheck />
                                                                    Priority
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-muted-foreground text-xs">
                                                            Employee ID {manifestEmployeeIdentifier(entry)}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>{manifestEmployeeDepartment(entry) ?? '—'}</TableCell>
                                                    <TableCell>
                                                        <AttendanceStatusBadge status={status} />
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground text-xs">
                                                        <p>{entry.boarded_at ? formatBoardedAt(entry.boarded_at) : '—'}</p>
                                                        {entry.recording_method && (
                                                            <p className="mt-0.5">
                                                                {recordingMethodLabel(entry.recording_method)}
                                                                {entry.recorded_by?.name ? ` · ${entry.recorded_by.name}` : ''}
                                                            </p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {editable &&
                                                            (status === 'BOARDED' ? (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    disabled={isUpdating}
                                                                    onClick={() => updateAttendance(entry, 'UNMARKED')}
                                                                >
                                                                    {isUpdating ? <LoaderCircle className="animate-spin" /> : <Undo2 />}
                                                                    Unmark
                                                                </Button>
                                                            ) : (
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    disabled={isUpdating}
                                                                    onClick={() => updateAttendance(entry, 'BOARDED')}
                                                                >
                                                                    {isUpdating ? <LoaderCircle className="animate-spin" /> : <UserCheck />}
                                                                    Boarded
                                                                </Button>
                                                            ))}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>

                            <div className="grid gap-3 p-4 lg:hidden">
                                {visibleEntries.map((entry) => {
                                    const status = manifestStatus(entry);
                                    const id = reservationId(entry);
                                    const isUpdating = id !== null && updatingReservationId === id;

                                    return (
                                        <div key={`${manifestEmployeeIdentifier(entry)}-${entry.seat_number}`} className="rounded-xl border p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="font-semibold">{manifestEmployeeName(entry)}</p>
                                                        {manifestEmployeePriority(entry) === 'PRIORITY' && (
                                                            <Badge variant="outline" className="border-amber-200 bg-amber-50 text-amber-800">
                                                                Priority
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-muted-foreground mt-1 text-xs">
                                                        Seat #{entry.seat_number} · Employee ID {manifestEmployeeIdentifier(entry)} ·{' '}
                                                        {manifestEmployeeDepartment(entry) ?? 'No department'}
                                                    </p>
                                                </div>
                                                <AttendanceStatusBadge status={status} />
                                            </div>
                                            {editable && (
                                                <Button
                                                    type="button"
                                                    variant={status === 'BOARDED' ? 'ghost' : 'outline'}
                                                    size="sm"
                                                    className="mt-3 w-full"
                                                    disabled={isUpdating}
                                                    onClick={() => updateAttendance(entry, status === 'BOARDED' ? 'UNMARKED' : 'BOARDED')}
                                                >
                                                    {isUpdating ? (
                                                        <LoaderCircle className="animate-spin" />
                                                    ) : status === 'BOARDED' ? (
                                                        <Undo2 />
                                                    ) : (
                                                        <UserCheck />
                                                    )}
                                                    {status === 'BOARDED' ? 'Return to unmarked' : 'Mark boarded'}
                                                </Button>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            {editable && pendingCount > 0 && (
                <Alert className="border-blue-200 bg-blue-50/70 dark:border-blue-900 dark:bg-blue-950/30">
                    <CircleAlert className="text-blue-700 dark:text-blue-300" />
                    <AlertDescription>
                        Any passenger left unmarked becomes a no-show when this service is completed. Not-operated services are classified separately.
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
}

function ScanLineIcon() {
    return (
        <span className="bg-primary/10 text-primary rounded-lg p-1.5">
            <UserCheck className="size-4" />
        </span>
    );
}
