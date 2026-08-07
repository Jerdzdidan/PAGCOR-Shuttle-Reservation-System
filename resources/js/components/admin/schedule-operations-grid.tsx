import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import {
    Armchair,
    ArrowRight,
    BusFront,
    CalendarDays,
    CircleGauge,
    Clock3,
    MapPin,
    ScanLine,
    Settings2,
    ShieldCheck,
    UserRound,
    UsersRound,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type PriorityStatus = 'REGULAR' | 'PRIORITY';
type Direction = 'OUTBOUND' | 'RETURN';
type ActivityFilter = 'WITH_ACTIVITY' | 'ALL';

interface OccurrenceEmployee {
    employee_id: number;
    employee_code: string;
    name: string;
    department: string | null;
    priority_status: PriorityStatus;
}

export interface ScheduleOccurrence {
    id: number;
    occurrence_id: number | null;
    lifecycle_state: 'SCHEDULED' | 'AWAITING_COMPLETION' | 'COMPLETED' | 'NOT_OPERATED' | null;
    direction: Direction;
    departure_time: string;
    departure_at: string;
    operational_status: string;
    route: {
        name: string;
        origin: string;
        destination: string;
    };
    vehicle: {
        plate_number: string;
        vehicle_type: string;
        capacity: number;
    };
    driver: {
        name: string;
    };
    effective_capacity: number;
    usable_seat_count: number;
    priority_seats: number[];
    unavailable_seats: number[];
    reservations: Array<{
        id: number;
        seat_number: number;
        source: string;
        employee: OccurrenceEmployee;
    }>;
    waitlist: Array<{
        id: number;
        position: number;
        queued_at: string;
        employee: OccurrenceEmployee;
    }>;
    reserved_count: number;
    attendance_totals: {
        boarded: number;
        no_show: number;
        service_not_operated: number;
        unmarked: number;
    };
    available_count: number;
    queue_size: number;
    waitlist_enabled: boolean;
    waitlist_capacity: number | null;
}

interface ScheduleOperationsGridProps {
    selectedDate: string;
    occurrences: ScheduleOccurrence[];
    operatingTimezone: string;
    onDateChange: (date: string) => void;
    onConfigure: (scheduleId: number) => void;
}

function displayTime(time: string): string {
    const [hourValue, minute = '00'] = time.split(':');
    const hour = Number(hourValue);

    return `${hour % 12 || 12}:${minute} ${hour >= 12 ? 'PM' : 'AM'}`;
}

function displayTravelDate(date: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00+08:00`));
}

function dateValueInTimezone(date: Date): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(date);
    const value = Object.fromEntries(parts.map((part) => [part.type, part.value]));

    return `${value.year}-${value.month}-${value.day}`;
}

function upcomingDates(): Array<{ date: string; dayLabel: string; label: string }> {
    const manilaToday = dateValueInTimezone(new Date());
    const start = new Date(`${manilaToday}T00:00:00+08:00`);

    return Array.from({ length: 31 }, (_, index) => {
        const date = new Date(start.getTime() + index * 86_400_000);

        return {
            date: dateValueInTimezone(date),
            dayLabel: new Intl.DateTimeFormat('en-PH', {
                timeZone: 'Asia/Manila',
                weekday: 'short',
            }).format(date),
            label: new Intl.DateTimeFormat('en-PH', {
                timeZone: 'Asia/Manila',
                month: 'short',
                day: 'numeric',
            }).format(date),
        };
    });
}

function routeEndpoints(occurrence: ScheduleOccurrence): [string, string] {
    return occurrence.direction === 'RETURN'
        ? [occurrence.route.destination, occurrence.route.origin]
        : [occurrence.route.origin, occurrence.route.destination];
}

function operationalStatusLabel(status: string): string {
    return status
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function statusBadgeClass(status: string): string {
    if (status === 'DEPARTED' || status === 'COMPLETED') {
        return 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300';
    }

    if (status === 'BOOKING_CLOSED' || status === 'CLOSED' || status === 'AWAITING_COMPLETION') {
        return 'border-amber-200 bg-amber-100 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200';
    }

    if (status === 'CANCELLED' || status === 'INACTIVE' || status === 'UNAVAILABLE' || status === 'NOT_OPERATED') {
        return 'border-red-200 bg-red-100 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200';
    }

    return 'border-emerald-200 bg-emerald-100 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200';
}

function SeatLegend({ className, label }: { className: string; label: string }) {
    return (
        <span className="text-muted-foreground flex items-center gap-2 text-xs">
            <span className={cn('size-4 rounded border', className)} />
            {label}
        </span>
    );
}

function OccurrenceSeatMap({ occurrence }: { occurrence: ScheduleOccurrence }) {
    const [selectedSeat, setSelectedSeat] = useState<number | null>(null);
    const reservationsBySeat = useMemo(
        () => new Map(occurrence.reservations.map((reservation) => [reservation.seat_number, reservation])),
        [occurrence.reservations],
    );
    const prioritySeats = useMemo(() => new Set(occurrence.priority_seats), [occurrence.priority_seats]);
    const unavailableSeats = useMemo(() => new Set(occurrence.unavailable_seats), [occurrence.unavailable_seats]);
    const selectedReservation = selectedSeat === null ? null : reservationsBySeat.get(selectedSeat);
    const rows = Array.from({ length: Math.ceil(occurrence.effective_capacity / 4) }, (_, index) => {
        const firstSeat = index * 4 + 1;

        return [firstSeat, firstSeat + 1, firstSeat + 2, firstSeat + 3];
    });

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-x-4 gap-y-2">
                <SeatLegend className="bg-background" label="General" />
                <SeatLegend className="border-amber-300 bg-amber-100 dark:border-amber-800 dark:bg-amber-950" label="Priority" />
                <SeatLegend className="border-blue-300 bg-blue-100 dark:border-blue-800 dark:bg-blue-950" label="Occupied" />
                <SeatLegend className="border-muted-foreground/40 border-dashed bg-transparent" label="Unavailable" />
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(18rem,0.8fr)_minmax(18rem,1.2fr)]">
                <div className="border-border bg-muted/30 mx-auto w-full max-w-sm rounded-[2.5rem] border-2 p-4 shadow-inner sm:p-5">
                    <div className="mb-5 grid grid-cols-[1fr_1fr_0.45fr_1fr_1fr] items-center gap-2 rounded-t-[1.65rem] border-b bg-blue-50 p-3 dark:bg-blue-950/30">
                        <div className="bg-background col-span-2 flex h-12 items-center justify-center gap-2 rounded-xl border text-xs font-medium">
                            <CircleGauge className="size-4" />
                            Driver
                        </div>
                        <div className="col-span-3 flex h-12 items-center justify-center rounded-xl border border-blue-200 bg-blue-100/80 text-xs font-medium tracking-wider text-blue-900 uppercase dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
                            <BusFront className="mr-2 size-4" />
                            Windshield
                        </div>
                    </div>

                    <div className="space-y-3">
                        {rows.map((row, rowIndex) => (
                            <div key={rowIndex} className="grid grid-cols-[1fr_1fr_0.45fr_1fr_1fr] items-center gap-2">
                                {row.map((seatNumber, seatIndex) => {
                                    const isOutsideCapacity = seatNumber > occurrence.effective_capacity;
                                    const isUnavailable = unavailableSeats.has(seatNumber);
                                    const isPriority = prioritySeats.has(seatNumber);
                                    const reservation = reservationsBySeat.get(seatNumber);
                                    const isSelected = selectedSeat === seatNumber;

                                    return (
                                        <button
                                            key={seatNumber}
                                            type="button"
                                            disabled={isOutsideCapacity}
                                            onClick={() => setSelectedSeat(seatNumber)}
                                            aria-label={
                                                isOutsideCapacity
                                                    ? 'No physical seat'
                                                    : reservation
                                                      ? `Seat ${seatNumber}, occupied by ${reservation.employee.name}`
                                                      : isUnavailable
                                                        ? `Seat ${seatNumber}, unavailable`
                                                        : isPriority
                                                          ? `Seat ${seatNumber}, priority`
                                                          : `Seat ${seatNumber}, general`
                                            }
                                            className={cn(
                                                'focus-visible:ring-ring relative flex aspect-square min-h-13 items-center justify-center rounded-xl border text-sm font-bold shadow-xs transition-all focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden',
                                                seatIndex === 2 && 'col-start-4',
                                                !isUnavailable && !isPriority && !reservation && 'bg-background',
                                                isPriority &&
                                                    !reservation &&
                                                    'border-amber-300 bg-amber-100 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
                                                isUnavailable &&
                                                    'border-muted-foreground/35 text-muted-foreground border-dashed bg-transparent shadow-none',
                                                reservation &&
                                                    'border-blue-300 bg-blue-100 text-blue-950 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100',
                                                !isOutsideCapacity && 'hover:-translate-y-0.5 hover:shadow-md',
                                                isSelected && 'ring-primary/30 ring-2',
                                                isOutsideCapacity && 'cursor-not-allowed opacity-35',
                                            )}
                                        >
                                            {isPriority && <ShieldCheck className="absolute top-1 right-1 size-3 opacity-75" />}
                                            {isUnavailable && <X className="absolute top-1 right-1 size-3 opacity-75" />}
                                            {isOutsideCapacity ? '—' : seatNumber}
                                        </button>
                                    );
                                })}
                            </div>
                        ))}
                    </div>

                    <div className="text-muted-foreground mt-5 flex items-center justify-center gap-2 border-t pt-4 text-xs font-medium tracking-wider uppercase">
                        <span className="bg-border h-px w-8" />
                        Rear
                        <span className="bg-border h-px w-8" />
                    </div>
                </div>

                <div className="space-y-3">
                    <div className="bg-muted/30 min-h-28 rounded-xl border p-4">
                        <p className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Selected seat</p>
                        {selectedSeat === null ? (
                            <p className="text-muted-foreground mt-3 text-sm">Select a seat to inspect its allocation or occupant.</p>
                        ) : selectedReservation ? (
                            <div className="mt-3 space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-semibold">
                                        Seat {selectedSeat} · {selectedReservation.employee.name}
                                    </p>
                                    <Badge variant={selectedReservation.employee.priority_status === 'PRIORITY' ? 'default' : 'secondary'}>
                                        {selectedReservation.employee.priority_status === 'PRIORITY' ? 'Priority' : 'Regular'}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    Employee ID {selectedReservation.employee.employee_code}
                                    {selectedReservation.employee.department ? ` · ${selectedReservation.employee.department}` : ''}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    Reservation source: {selectedReservation.source.replaceAll('_', ' ').toLowerCase()}
                                </p>
                            </div>
                        ) : (
                            <div className="mt-3">
                                <p className="font-semibold">Seat {selectedSeat}</p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {unavailableSeats.has(selectedSeat)
                                        ? 'Unavailable for reservation'
                                        : prioritySeats.has(selectedSeat)
                                          ? 'Available to priority employees'
                                          : 'Available general seat'}
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="overflow-hidden rounded-xl border">
                        <div className="bg-muted/35 flex items-center justify-between gap-3 border-b px-4 py-3">
                            <p className="text-sm font-semibold">Confirmed manifest</p>
                            <Badge variant="secondary">{occurrence.reserved_count}</Badge>
                        </div>
                        {occurrence.reservations.length === 0 ? (
                            <p className="text-muted-foreground px-4 py-6 text-center text-sm">No confirmed passengers.</p>
                        ) : (
                            <div className="max-h-72 divide-y overflow-y-auto">
                                {[...occurrence.reservations]
                                    .sort((left, right) => left.seat_number - right.seat_number)
                                    .map((reservation) => (
                                        <button
                                            key={reservation.id}
                                            type="button"
                                            onClick={() => setSelectedSeat(reservation.seat_number)}
                                            className="hover:bg-muted/40 flex w-full items-center gap-3 px-4 py-3 text-left transition-colors"
                                        >
                                            <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                                                {reservation.seat_number}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium">{reservation.employee.name}</span>
                                                <span className="text-muted-foreground block truncate text-xs">
                                                    {reservation.employee.employee_code}
                                                    {reservation.employee.department ? ` · ${reservation.employee.department}` : ''}
                                                </span>
                                            </span>
                                            {reservation.employee.priority_status === 'PRIORITY' && (
                                                <ShieldCheck className="size-4 shrink-0 text-amber-600" />
                                            )}
                                        </button>
                                    ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

function OccurrenceDetail({
    occurrence,
    open,
    onOpenChange,
}: {
    occurrence: ScheduleOccurrence | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!occurrence) {
        return null;
    }

    const [origin, destination] = routeEndpoints(occurrence);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex h-full w-full flex-col overflow-y-auto p-0 sm:max-w-4xl">
                <SheetHeader className="border-b px-5 py-5 pr-14 sm:px-7">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{occurrence.direction === 'OUTBOUND' ? 'Outbound' : 'Return'}</Badge>
                        <Badge className={statusBadgeClass(occurrence.operational_status)}>
                            {operationalStatusLabel(occurrence.operational_status)}
                        </Badge>
                    </div>
                    <SheetTitle>{occurrence.route.name}</SheetTitle>
                    <SheetDescription>
                        {origin} to {destination} · {displayTime(occurrence.departure_time)} · {occurrence.vehicle.plate_number}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 space-y-6 px-5 py-6 sm:px-7">
                    {occurrence.occurrence_id !== null &&
                        occurrence.lifecycle_state !== 'COMPLETED' &&
                        occurrence.lifecycle_state !== 'NOT_OPERATED' && (
                            <div className="flex flex-col justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 sm:flex-row sm:items-center dark:border-blue-900 dark:bg-blue-950/35">
                                <div>
                                    <p className="font-semibold text-blue-950 dark:text-blue-100">Passenger boarding is open</p>
                                    <p className="text-sm text-blue-800/75 dark:text-blue-200/70">
                                        Scan reserved employees or update the manifest manually.
                                    </p>
                                </div>
                                <Button asChild>
                                    <Link href={`/admin/finished-services?occurrence=${occurrence.occurrence_id}`}>
                                        <ScanLine />
                                        Board passengers
                                    </Link>
                                </Button>
                            </div>
                        )}

                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="bg-muted/35 rounded-xl border p-4">
                            <p className="text-muted-foreground text-xs">Usable seats</p>
                            <p className="mt-1 text-xl font-semibold">{occurrence.usable_seat_count}</p>
                        </div>
                        <div className="bg-muted/35 rounded-xl border p-4">
                            <p className="text-muted-foreground text-xs">Reserved</p>
                            <p className="mt-1 text-xl font-semibold">{occurrence.reserved_count}</p>
                        </div>
                        <div className="bg-muted/35 rounded-xl border p-4">
                            <p className="text-muted-foreground text-xs">Available</p>
                            <p className="mt-1 text-xl font-semibold">{occurrence.available_count}</p>
                        </div>
                        <div className="bg-muted/35 rounded-xl border p-4">
                            <p className="text-muted-foreground text-xs">Waitlist</p>
                            <p className="mt-1 text-xl font-semibold">{occurrence.queue_size}</p>
                        </div>
                        <div className="bg-muted/35 rounded-xl border p-4">
                            <p className="text-muted-foreground text-xs">Boarded</p>
                            <p className="mt-1 text-xl font-semibold">{occurrence.attendance_totals.boarded}</p>
                        </div>
                    </div>

                    <section className="space-y-3">
                        <div>
                            <h3 className="text-lg font-semibold">Seat map and manifest</h3>
                            <p className="text-muted-foreground text-sm">Select any seat to inspect its allocation and reservation details.</p>
                        </div>
                        <OccurrenceSeatMap occurrence={occurrence} />
                    </section>

                    <section className="space-y-3">
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-semibold">Waitlist order</h3>
                                <p className="text-muted-foreground text-sm">Priority tier first, then first-come, first-served.</p>
                            </div>
                            <Badge variant={occurrence.waitlist_enabled ? 'secondary' : 'outline'}>
                                {occurrence.waitlist_enabled
                                    ? occurrence.waitlist_capacity === null
                                        ? 'Enabled · no limit'
                                        : `Enabled · max ${occurrence.waitlist_capacity}`
                                    : 'Disabled'}
                            </Badge>
                        </div>

                        <div className="overflow-hidden rounded-xl border">
                            {occurrence.waitlist.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 px-5 py-10 text-center">
                                    <UsersRound className="text-muted-foreground size-6" />
                                    <p className="font-medium">No employees are waiting.</p>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {[...occurrence.waitlist]
                                        .sort((left, right) => left.position - right.position)
                                        .map((entry) => (
                                            <div key={entry.id} className="flex items-center gap-3 px-4 py-3">
                                                <span className="bg-muted flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold">
                                                    {entry.position}
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-medium">{entry.employee.name}</span>
                                                    <span className="text-muted-foreground block truncate text-xs">
                                                        {entry.employee.employee_code}
                                                        {entry.employee.department ? ` · ${entry.employee.department}` : ''}
                                                    </span>
                                                </span>
                                                <Badge variant={entry.employee.priority_status === 'PRIORITY' ? 'default' : 'secondary'}>
                                                    {entry.employee.priority_status === 'PRIORITY' ? 'Priority' : 'Regular'}
                                                </Badge>
                                            </div>
                                        ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function OccurrenceCard({ occurrence, onView, onConfigure }: { occurrence: ScheduleOccurrence; onView: () => void; onConfigure: () => void }) {
    const [origin, destination] = routeEndpoints(occurrence);
    const occupancyPercentage =
        occurrence.usable_seat_count > 0 ? Math.min(100, Math.round((occurrence.reserved_count / occurrence.usable_seat_count) * 100)) : 100;
    const waitlistIsFull = occurrence.waitlist_capacity !== null && occurrence.queue_size >= occurrence.waitlist_capacity;

    return (
        <Card className="group overflow-hidden transition-shadow hover:shadow-md">
            <div
                className={cn(
                    'h-1',
                    occurrence.available_count === 0 ? (occurrence.queue_size > 0 ? 'bg-amber-500' : 'bg-destructive') : 'bg-primary',
                )}
            />
            <CardHeader className="gap-4 p-5 sm:p-6">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="border-primary/25 bg-primary/5 text-primary">
                                {occurrence.direction === 'OUTBOUND' ? 'Outbound' : 'Return'}
                            </Badge>
                            <Badge className={statusBadgeClass(occurrence.operational_status)}>
                                {operationalStatusLabel(occurrence.operational_status)}
                            </Badge>
                            {!occurrence.waitlist_enabled && <Badge variant="outline">Waitlist disabled</Badge>}
                            {waitlistIsFull && <Badge variant="destructive">Waitlist full</Badge>}
                        </div>
                        <h2 className="mt-3 truncate text-lg font-semibold">{occurrence.route.name}</h2>
                    </div>
                    <div className="bg-primary/8 rounded-xl px-4 py-2 text-left sm:text-right">
                        <p className="text-primary flex items-center gap-1.5 text-lg font-bold sm:justify-end">
                            <Clock3 className="size-4" />
                            {displayTime(occurrence.departure_time)}
                        </p>
                        <p className="text-muted-foreground text-xs">Asia/Manila</p>
                    </div>
                </div>

                <div className="bg-muted/20 grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-xl border p-4">
                    <span className="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-full">
                        <MapPin className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">{origin}</p>
                        <div className="text-muted-foreground my-1 flex items-center gap-2">
                            <span className="bg-border h-px flex-1" />
                            <ArrowRight className="size-3.5" />
                            <span className="bg-border h-px flex-1" />
                        </div>
                        <p className="truncate text-sm font-semibold">{destination}</p>
                    </div>
                    <BusFront className="text-muted-foreground size-6" />
                </div>
            </CardHeader>

            <CardContent className="space-y-5 px-5 pb-5 sm:px-6">
                <div className="grid gap-3 text-sm sm:grid-cols-3">
                    <div className="bg-muted/45 rounded-xl p-3">
                        <p className="text-muted-foreground text-xs">Vehicle</p>
                        <p className="mt-1 font-semibold">{occurrence.vehicle.plate_number}</p>
                        <p className="text-muted-foreground truncate text-xs">{occurrence.vehicle.vehicle_type}</p>
                    </div>
                    <div className="bg-muted/45 rounded-xl p-3">
                        <p className="text-muted-foreground text-xs">Driver</p>
                        <p className="mt-1 truncate font-semibold">{occurrence.driver.name}</p>
                        <p className="text-muted-foreground text-xs">Assigned driver</p>
                    </div>
                    <div className="bg-muted/45 rounded-xl p-3">
                        <p className="text-muted-foreground text-xs">Waitlist</p>
                        <p className="mt-1 font-semibold">
                            {occurrence.queue_size}
                            {occurrence.waitlist_capacity !== null ? ` / ${occurrence.waitlist_capacity}` : ''}
                        </p>
                        <p className="text-muted-foreground text-xs">{occurrence.waitlist_enabled ? 'Queue enabled' : 'Queue disabled'}</p>
                    </div>
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-3 text-xs">
                        <span className="font-medium">
                            {occurrence.available_count === 0
                                ? 'No usable seats available'
                                : `${occurrence.available_count} seat${occurrence.available_count === 1 ? '' : 's'} available`}
                        </span>
                        <span className="text-muted-foreground">
                            {occurrence.reserved_count}/{occurrence.usable_seat_count} booked
                        </span>
                    </div>
                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                        <div
                            className={cn(
                                'h-full rounded-full transition-[width]',
                                occurrence.available_count === 0 ? 'bg-destructive' : occupancyPercentage >= 75 ? 'bg-amber-500' : 'bg-primary',
                            )}
                            style={{ width: `${occupancyPercentage}%` }}
                        />
                    </div>
                </div>

                <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                    <span className="flex items-center gap-1.5">
                        <Armchair className="size-3.5" />
                        {occurrence.usable_seat_count} usable
                    </span>
                    <span className="flex items-center gap-1.5">
                        <ShieldCheck className="size-3.5" />
                        {occurrence.priority_seats.length} priority
                    </span>
                    <span className="flex items-center gap-1.5">
                        <X className="size-3.5" />
                        {occurrence.unavailable_seats.length} unavailable
                    </span>
                </div>
            </CardContent>

            <CardFooter className="bg-muted/15 flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <Button type="button" variant="outline" onClick={onView}>
                    <UserRound />
                    View seats &amp; manifest
                </Button>
                <Button type="button" variant="ghost" onClick={onConfigure}>
                    <Settings2 />
                    Configure
                </Button>
            </CardFooter>
        </Card>
    );
}

export function ScheduleOperationsGrid({ selectedDate, occurrences, operatingTimezone, onDateChange, onConfigure }: ScheduleOperationsGridProps) {
    const [routeFilter, setRouteFilter] = useState('ALL');
    const [directionFilter, setDirectionFilter] = useState('ALL');
    const [activityFilter, setActivityFilter] = useState<ActivityFilter>('ALL');
    const [viewingOccurrenceId, setViewingOccurrenceId] = useState<number | null>(null);
    const dateOptions = useMemo(upcomingDates, []);
    const routeNames = useMemo(() => [...new Set(occurrences.map((occurrence) => occurrence.route.name))].sort(), [occurrences]);
    const filteredOccurrences = occurrences.filter((occurrence) => {
        const routeMatches = routeFilter === 'ALL' || occurrence.route.name === routeFilter;
        const directionMatches = directionFilter === 'ALL' || occurrence.direction === directionFilter;
        const activityMatches = activityFilter === 'ALL' || occurrence.reserved_count > 0 || occurrence.queue_size > 0;

        return routeMatches && directionMatches && activityMatches;
    });
    const viewingOccurrence = occurrences.find((occurrence) => occurrence.id === viewingOccurrenceId) ?? null;

    return (
        <div className="max-w-full min-w-0 space-y-6">
            <Card className="max-w-full min-w-0 overflow-hidden">
                <CardHeader className="bg-muted/20 border-b p-4 sm:p-5">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-2">
                            <CalendarDays className="text-primary size-5" />
                            <div>
                                <h2 className="font-semibold">Inspect a travel date</h2>
                                <p className="text-muted-foreground text-xs">Live occurrence data refreshes every 10 seconds · {operatingTimezone}</p>
                            </div>
                        </div>
                        <div className="w-full space-y-1.5 sm:w-44">
                            <Label htmlFor="admin-schedule-date" className="sr-only">
                                Travel date
                            </Label>
                            <Input
                                id="admin-schedule-date"
                                type="date"
                                value={selectedDate}
                                onChange={(event) => event.target.value && onDateChange(event.target.value)}
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="min-w-0 overflow-hidden p-3 sm:p-4">
                    <div className="[&::-webkit-scrollbar-thumb]:bg-border hover:[&::-webkit-scrollbar-thumb]:bg-muted-foreground/60 flex w-full max-w-full min-w-0 touch-pan-x snap-x gap-2 overflow-x-auto overscroll-x-contain pb-3 [scrollbar-color:var(--color-border)_transparent] [scrollbar-gutter:stable] [scrollbar-width:thin] [&::-webkit-scrollbar]:h-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:transition-colors [&::-webkit-scrollbar-track]:bg-transparent">
                        {dateOptions.map((date) => {
                            const isSelected = date.date === selectedDate;

                            return (
                                <button
                                    key={date.date}
                                    type="button"
                                    onClick={() => onDateChange(date.date)}
                                    className={cn(
                                        'min-w-22 snap-start rounded-xl border px-3 py-3 text-center transition-colors',
                                        isSelected
                                            ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                            : 'bg-background hover:border-primary/50 hover:bg-accent',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'block text-[0.65rem] font-semibold tracking-wider uppercase',
                                            !isSelected && 'text-muted-foreground',
                                        )}
                                    >
                                        {date.dayLabel}
                                    </span>
                                    <span className="mt-1 block text-sm font-semibold">{date.label}</span>
                                </button>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>

            <div className="flex flex-col gap-4 lg:flex-row lg:items-end">
                <div>
                    <p className="text-primary text-sm font-medium">{displayTravelDate(selectedDate)}</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight">Schedule operations</h2>
                    <p className="text-muted-foreground text-sm">
                        {filteredOccurrences.length} date-specific occurrence{filteredOccurrences.length === 1 ? '' : 's'} shown
                    </p>
                </div>
                <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end sm:gap-1.5 lg:ml-auto lg:w-auto">
                    <Select value={activityFilter} onValueChange={(value) => setActivityFilter(value as ActivityFilter)}>
                        <SelectTrigger className="w-full sm:w-52">
                            <SelectValue placeholder="Activity" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="WITH_ACTIVITY">Reservations &amp; queues</SelectItem>
                            <SelectItem value="ALL">All scheduled departures</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={routeFilter} onValueChange={setRouteFilter}>
                        <SelectTrigger className="w-full sm:w-48">
                            <SelectValue placeholder="All routes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="ALL">All routes</SelectItem>
                            {routeNames.map((routeName) => (
                                <SelectItem key={routeName} value={routeName}>
                                    {routeName}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={directionFilter} onValueChange={setDirectionFilter}>
                        <SelectTrigger className="w-full sm:w-40">
                            <SelectValue placeholder="Direction" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="ALL">All directions</SelectItem>
                            <SelectItem value="OUTBOUND">Outbound</SelectItem>
                            <SelectItem value="RETURN">Return</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {filteredOccurrences.length === 0 ? (
                <Card className="border-dashed">
                    <CardContent className="flex flex-col items-center justify-center px-6 py-16 text-center">
                        <span className="bg-muted mb-4 flex size-14 items-center justify-center rounded-2xl">
                            <BusFront className="text-muted-foreground size-6" />
                        </span>
                        <h2 className="text-lg font-semibold">No schedule occurrences found</h2>
                        <p className="text-muted-foreground mt-1 max-w-sm text-sm leading-6">
                            {activityFilter === 'WITH_ACTIVITY'
                                ? 'There are no reservations or waitlist entries for this travel date and filter combination.'
                                : 'There are no active departures matching this travel date and filter combination.'}
                        </p>
                        {(activityFilter !== 'ALL' || routeFilter !== 'ALL' || directionFilter !== 'ALL') && (
                            <Button
                                type="button"
                                variant="outline"
                                className="mt-5"
                                onClick={() => {
                                    setActivityFilter('ALL');
                                    setRouteFilter('ALL');
                                    setDirectionFilter('ALL');
                                }}
                            >
                                Show all scheduled departures
                            </Button>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-5 xl:grid-cols-2">
                    {filteredOccurrences.map((occurrence) => (
                        <OccurrenceCard
                            key={occurrence.id}
                            occurrence={occurrence}
                            onView={() => setViewingOccurrenceId(occurrence.id)}
                            onConfigure={() => onConfigure(occurrence.id)}
                        />
                    ))}
                </div>
            )}

            <OccurrenceDetail
                occurrence={viewingOccurrence}
                open={viewingOccurrence !== null}
                onOpenChange={(open) => !open && setViewingOccurrenceId(null)}
            />
        </div>
    );
}
