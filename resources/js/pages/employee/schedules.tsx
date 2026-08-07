import { SeatSelector } from '@/components/employee/seat-selector';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import EmployeeLayout from '@/layouts/employee-layout';
import { cn } from '@/lib/utils';
import { Head, router, useForm, usePage, usePoll } from '@inertiajs/react';
import {
    Armchair,
    ArrowRight,
    BusFront,
    CalendarDays,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    MapPin,
    RefreshCw,
    ShieldCheck,
    TicketCheck,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

type PriorityStatus = 'REGULAR' | 'PRIORITY';
type ScheduleDirection = 'OUTBOUND' | 'RETURN';

interface WaitlistForm {
    [key: string]: string | number;
    schedule_id: number;
    travel_date: string;
}

interface EmployeeIdentity {
    employee_id: number;
    name: string;
    priority_status: PriorityStatus;
}

interface EmployeeSharedProps {
    auth: {
        employee: EmployeeIdentity;
    };
    [key: string]: unknown;
}

interface ScheduleOccurrence {
    id: number;
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
    direction: ScheduleDirection;
    departure_time: string;
    effective_capacity: number;
    priority_seats?: number[];
    unavailable_seats?: number[];
    priority_seat_count?: number;
    occupied_seats: number[];
    available_eligible_seats: number;
    eligible_capacity: number;
    is_full_for_employee: boolean;
    reservation: null | {
        id: number;
        seat_number: number;
        source: string;
    };
    waitlist: null | {
        id: number;
        position: number;
        tier: string;
    };
    queue_size: number;
    waitlist_enabled?: boolean;
    waitlist_capacity?: number | null;
    booking_open?: boolean;
}

interface DailyEntry {
    type: 'RESERVATION' | 'WAITLIST';
    schedule_id: number;
    route_name: string;
    departure_time: string;
}

interface SchedulesPageProps {
    selectedDate: string;
    dates: Array<{
        date: string;
        label: string;
        dayLabel: string;
    }>;
    schedules: ScheduleOccurrence[];
    dailyEntry?: DailyEntry | null;
    operating_timezone?: string;
}

function displayTime(time: string): string {
    const [hourValue, minute = '00'] = time.split(':');
    const hour = Number(hourValue);
    const meridiem = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;

    return `${displayHour}:${minute} ${meridiem}`;
}

function displayTravelDate(date: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

function routeEndpoints(schedule: ScheduleOccurrence): [string, string] {
    if (schedule.direction === 'RETURN') {
        return [schedule.route.destination, schedule.route.origin];
    }

    return [schedule.route.origin, schedule.route.destination];
}

function CancelAction({
    endpoint,
    title,
    description,
    buttonLabel,
    successMessage,
}: {
    endpoint: string;
    title: string;
    description: string;
    buttonLabel: string;
    successMessage: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({});

    function confirm(): void {
        form.delete(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                toast.success(successMessage);
            },
            onError: (errors) => {
                const message = Object.values(errors)[0];
                toast.error(typeof message === 'string' ? message : 'This request could not be completed.');
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={(nextOpen) => !form.processing && setOpen(nextOpen)}>
            <Button type="button" variant="outline" onClick={() => setOpen(true)}>
                {buttonLabel}
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={form.processing}>
                        Keep it
                    </Button>
                    <Button type="button" variant="destructive" onClick={confirm} disabled={form.processing}>
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        Confirm cancellation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function JoinWaitlistButton({ scheduleId, travelDate }: { scheduleId: number; travelDate: string }) {
    const form = useForm<WaitlistForm>({
        schedule_id: scheduleId,
        travel_date: travelDate,
    });
    const error = Object.values(form.errors)[0];

    function join(): void {
        form.post('/employee/waitlist', {
            preserveScroll: true,
            onSuccess: () => toast.success('You joined the waitlist. Your queue position will update automatically.'),
        });
    }

    return (
        <div className="space-y-2 sm:text-right">
            <Button type="button" onClick={join} disabled={form.processing} className="w-full sm:w-auto">
                {form.processing ? <LoaderCircle className="animate-spin" /> : <UsersRound />}
                Join waitlist
            </Button>
            {error && <p className="text-destructive text-xs font-medium">{error}</p>}
        </div>
    );
}

function ScheduleCard({
    schedule,
    selectedDate,
    priorityStatus,
    dailyEntry,
}: {
    schedule: ScheduleOccurrence;
    selectedDate: string;
    priorityStatus: PriorityStatus;
    dailyEntry: DailyEntry | null;
}) {
    const [origin, destination] = routeEndpoints(schedule);
    const usedEligibleSeats = Math.max(0, schedule.eligible_capacity - schedule.available_eligible_seats);
    const occupancyPercentage =
        schedule.eligible_capacity > 0 ? Math.min(100, Math.round((usedEligibleSeats / schedule.eligible_capacity) * 100)) : 100;
    const isAutoAssigned = schedule.reservation?.source === 'AUTO_ASSIGNED';
    const bookingOpen = schedule.booking_open !== false;
    const prioritySeats = schedule.priority_seats ?? Array.from({ length: schedule.priority_seat_count ?? 0 }, (_, index) => index + 1);
    const unavailableSeats = schedule.unavailable_seats ?? [];
    const waitlistEnabled = schedule.waitlist_enabled !== false;
    const waitlistIsFull =
        schedule.waitlist_capacity !== null && schedule.waitlist_capacity !== undefined && schedule.queue_size >= schedule.waitlist_capacity;
    const blockedByOtherBooking = dailyEntry !== null && dailyEntry.schedule_id !== schedule.id && !schedule.reservation && !schedule.waitlist;
    const blockedMessage =
        dailyEntry === null
            ? ''
            : dailyEntry.type === 'RESERVATION'
              ? `You already have a seat on ${dailyEntry.route_name} at ${displayTime(dailyEntry.departure_time)} today. Only one schedule per day can be booked.`
              : `You are on the ${dailyEntry.route_name} waitlist for ${displayTime(dailyEntry.departure_time)} today. Only one schedule per day can be booked.`;

    return (
        <Card className="group overflow-hidden transition-shadow hover:shadow-md">
            <div
                className={cn(
                    'h-1',
                    schedule.reservation
                        ? 'bg-emerald-500'
                        : schedule.waitlist
                          ? 'bg-amber-500'
                          : schedule.is_full_for_employee
                            ? 'bg-destructive'
                            : 'bg-primary',
                )}
            />
            <CardHeader className="gap-4 p-5 sm:p-6">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" className="border-primary/25 bg-primary/5 text-primary">
                                {schedule.direction === 'OUTBOUND' ? 'Outbound' : 'Return'}
                            </Badge>
                            {schedule.reservation && (
                                <Badge className="border-emerald-200 bg-emerald-100 text-emerald-900 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                                    <CheckCircle2 className="mr-1 size-3" />
                                    Reserved
                                </Badge>
                            )}
                            {schedule.waitlist && (
                                <Badge className="border-amber-200 bg-amber-100 text-amber-900 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    <UsersRound className="mr-1 size-3" />
                                    Waitlisted
                                </Badge>
                            )}
                            {!bookingOpen && <Badge variant="secondary">Booking closed</Badge>}
                            {bookingOpen && !waitlistEnabled && <Badge variant="outline">Waitlist disabled</Badge>}
                            {bookingOpen && waitlistEnabled && waitlistIsFull && <Badge variant="destructive">Waitlist full</Badge>}
                        </div>
                        <h2 className="mt-3 truncate text-lg font-semibold">{schedule.route.name}</h2>
                    </div>
                    <div className="bg-primary/8 rounded-xl px-4 py-2 text-left sm:text-right">
                        <p className="text-primary flex items-center gap-1.5 text-lg font-bold sm:justify-end">
                            <Clock3 className="size-4" />
                            {displayTime(schedule.departure_time)}
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
                        <p className="mt-1 font-semibold">{schedule.vehicle.plate_number}</p>
                        <p className="text-muted-foreground truncate text-xs">{schedule.vehicle.vehicle_type}</p>
                    </div>
                    <div className="bg-muted/45 rounded-xl p-3">
                        <p className="text-muted-foreground text-xs">Driver</p>
                        <p className="mt-1 truncate font-semibold">{schedule.driver.name}</p>
                        <p className="text-muted-foreground text-xs">Assigned driver</p>
                    </div>
                    <div className="bg-muted/45 rounded-xl p-3">
                        <p className="text-muted-foreground text-xs">Capacity</p>
                        <p className="mt-1 font-semibold">{schedule.effective_capacity} seats</p>
                        <p className="text-muted-foreground text-xs">
                            {schedule.queue_size}
                            {schedule.waitlist_capacity !== null && schedule.waitlist_capacity !== undefined
                                ? ` / ${schedule.waitlist_capacity}`
                                : ''}{' '}
                            in queue
                        </p>
                    </div>
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-3 text-xs">
                        <span className="font-medium">
                            {schedule.is_full_for_employee
                                ? 'No eligible seats available'
                                : `${schedule.available_eligible_seats} eligible seat${schedule.available_eligible_seats === 1 ? '' : 's'} left`}
                        </span>
                        <span className="text-muted-foreground">
                            {usedEligibleSeats}/{schedule.eligible_capacity} occupied
                        </span>
                    </div>
                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                        <div
                            className={cn(
                                'h-full rounded-full transition-[width]',
                                schedule.is_full_for_employee ? 'bg-destructive' : occupancyPercentage >= 75 ? 'bg-amber-500' : 'bg-primary',
                            )}
                            style={{ width: `${occupancyPercentage}%` }}
                        />
                    </div>
                </div>

                {schedule.reservation && (
                    <Alert className="border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/35 dark:text-emerald-100">
                        <TicketCheck />
                        <AlertTitle className="flex flex-wrap items-center gap-2">
                            Seat {schedule.reservation.seat_number}
                            {isAutoAssigned && (
                                <Badge className="bg-emerald-700 text-white hover:bg-emerald-700">
                                    <RefreshCw className="mr-1 size-3" />
                                    Auto-assigned
                                </Badge>
                            )}
                        </AlertTitle>
                        <AlertDescription>Your reservation is confirmed for this schedule.</AlertDescription>
                    </Alert>
                )}

                {schedule.waitlist && (
                    <Alert className="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/35 dark:text-amber-100">
                        {priorityStatus === 'PRIORITY' ? <ShieldCheck /> : <UsersRound />}
                        <AlertTitle>
                            Queue position {schedule.waitlist.position} · {schedule.waitlist.tier.toLowerCase()} tier
                        </AlertTitle>
                        <AlertDescription>
                            If a seat opens, priority employees are assigned first, followed by regular employees in FCFS order.
                        </AlertDescription>
                    </Alert>
                )}
            </CardContent>

            <CardFooter className="bg-muted/15 flex flex-col items-stretch justify-between gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                <div className="text-muted-foreground flex items-center gap-2 text-xs">
                    <Armchair className="size-4" />
                    {prioritySeats.length} configured priority seat{prioritySeats.length === 1 ? '' : 's'}
                </div>

                {schedule.reservation && bookingOpen ? (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <SeatSelector
                            scheduleId={schedule.id}
                            travelDate={selectedDate}
                            capacity={schedule.effective_capacity}
                            prioritySeats={prioritySeats}
                            unavailableSeats={unavailableSeats}
                            prioritySeatCount={schedule.priority_seat_count}
                            occupiedSeats={schedule.occupied_seats}
                            priorityStatus={priorityStatus}
                            routeName={schedule.route.name}
                            departureTime={displayTime(schedule.departure_time)}
                            reservation={schedule.reservation}
                        />
                        <CancelAction
                            endpoint={`/employee/reservations/${schedule.reservation.id}`}
                            title="Cancel this reservation?"
                            description={`Seat ${schedule.reservation.seat_number} will be released. The next eligible employee in the queue may be assigned automatically.`}
                            buttonLabel="Cancel reservation"
                            successMessage="Your reservation was cancelled."
                        />
                    </div>
                ) : schedule.reservation ? (
                    <span className="text-muted-foreground text-sm font-medium">Departure has closed</span>
                ) : schedule.waitlist && bookingOpen ? (
                    <CancelAction
                        endpoint={`/employee/waitlist/${schedule.waitlist.id}`}
                        title="Leave this waitlist?"
                        description="You will lose your current queue position for this schedule."
                        buttonLabel="Leave waitlist"
                        successMessage="You left the waitlist."
                    />
                ) : schedule.waitlist ? (
                    <span className="text-muted-foreground text-sm font-medium">Departure has closed</span>
                ) : blockedByOtherBooking && bookingOpen ? (
                    <span className="text-muted-foreground max-w-xs text-sm font-medium sm:text-right">{blockedMessage}</span>
                ) : schedule.is_full_for_employee && bookingOpen && schedule.eligible_capacity > 0 && !waitlistEnabled ? (
                    <span className="text-muted-foreground text-sm font-medium">Waitlist is disabled for this schedule</span>
                ) : schedule.is_full_for_employee && bookingOpen && schedule.eligible_capacity > 0 && waitlistIsFull ? (
                    <span className="text-muted-foreground text-sm font-medium">Waitlist is full</span>
                ) : schedule.is_full_for_employee && bookingOpen && schedule.eligible_capacity > 0 ? (
                    <JoinWaitlistButton scheduleId={schedule.id} travelDate={selectedDate} />
                ) : bookingOpen && schedule.eligible_capacity > 0 ? (
                    <SeatSelector
                        scheduleId={schedule.id}
                        travelDate={selectedDate}
                        capacity={schedule.effective_capacity}
                        prioritySeats={prioritySeats}
                        unavailableSeats={unavailableSeats}
                        prioritySeatCount={schedule.priority_seat_count}
                        occupiedSeats={schedule.occupied_seats}
                        priorityStatus={priorityStatus}
                        routeName={schedule.route.name}
                        departureTime={displayTime(schedule.departure_time)}
                    />
                ) : bookingOpen ? (
                    <span className="text-muted-foreground text-sm font-medium">No general seats on this vehicle</span>
                ) : (
                    <span className="text-muted-foreground text-sm font-medium">Reservations are closed</span>
                )}
            </CardFooter>
        </Card>
    );
}

export default function EmployeeSchedules({
    selectedDate,
    dates,
    schedules,
    dailyEntry = null,
    operating_timezone = 'Asia/Manila',
}: SchedulesPageProps) {
    const { auth } = usePage<EmployeeSharedProps>().props;
    const [routeFilter, setRouteFilter] = useState('ALL');
    const [directionFilter, setDirectionFilter] = useState('ALL');

    usePoll(10000, {
        only: ['schedules', 'dailyEntry'],
    });

    const routeNames = useMemo(() => [...new Set(schedules.map((schedule) => schedule.route.name))].sort(), [schedules]);
    const filteredSchedules = schedules.filter((schedule) => {
        const routeMatches = routeFilter === 'ALL' || schedule.route.name === routeFilter;
        const directionMatches = directionFilter === 'ALL' || schedule.direction === directionFilter;

        return routeMatches && directionMatches;
    });

    function selectDate(date: string): void {
        if (date === selectedDate) {
            return;
        }

        router.get(
            '/employee/schedules',
            { date },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['selectedDate', 'dates', 'schedules', 'dailyEntry'],
            },
        );
    }

    return (
        <EmployeeLayout title="Shuttle schedules" description="Choose a route, departure, and your preferred seat.">
            <Head title="Employee schedules" />
            <div className="space-y-6">
                <Card className="overflow-hidden">
                    <CardHeader className="bg-muted/20 border-b p-4 sm:p-5">
                        <div className="flex items-center gap-2">
                            <CalendarDays className="text-primary size-5" />
                            <div>
                                <h2 className="font-semibold">Select travel date</h2>
                                <p className="text-muted-foreground text-xs">Reservations open up to 30 days ahead · {operating_timezone}</p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-3 sm:p-4">
                        <div className="flex snap-x gap-2 overflow-x-auto pb-1">
                            {dates.map((date) => {
                                const isSelected = date.date === selectedDate;

                                return (
                                    <button
                                        key={date.date}
                                        type="button"
                                        onClick={() => selectDate(date.date)}
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

                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-primary text-sm font-medium">{displayTravelDate(selectedDate)}</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight">Available departures</h2>
                        <p className="text-muted-foreground text-sm">
                            {filteredSchedules.length} schedule{filteredSchedules.length === 1 ? '' : 's'} shown
                        </p>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
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

                {filteredSchedules.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center px-6 py-16 text-center">
                            <span className="bg-muted mb-4 flex size-14 items-center justify-center rounded-2xl">
                                <BusFront className="text-muted-foreground size-6" />
                            </span>
                            <h2 className="text-lg font-semibold">No schedules found</h2>
                            <p className="text-muted-foreground mt-1 max-w-sm text-sm leading-6">
                                There are no active departures matching this date and filter combination.
                            </p>
                            {(routeFilter !== 'ALL' || directionFilter !== 'ALL') && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="mt-5"
                                    onClick={() => {
                                        setRouteFilter('ALL');
                                        setDirectionFilter('ALL');
                                    }}
                                >
                                    Clear filters
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-5 xl:grid-cols-2">
                        {filteredSchedules.map((schedule) => (
                            <ScheduleCard
                                key={schedule.id}
                                schedule={schedule}
                                selectedDate={selectedDate}
                                priorityStatus={auth.employee.priority_status}
                                dailyEntry={dailyEntry}
                            />
                        ))}
                    </div>
                )}

                <Alert>
                    <UserRound />
                    <AlertTitle>How booking works</AlertTitle>
                    <AlertDescription>
                        You may hold one schedule per travel date. Once booked, you can move to any open seat on that shuttle without cancelling. When
                        enabled for a schedule, the waitlist appears only when all seats you can use are occupied. Open seats are assigned to priority
                        employees first, then to regular employees in first-come, first-served order.
                    </AlertDescription>
                </Alert>
            </div>
        </EmployeeLayout>
    );
}
