import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import EmployeeLayout from '@/layouts/employee-layout';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import {
    Armchair,
    ArrowRight,
    BusFront,
    CalendarDays,
    Clock3,
    MapPin,
    RefreshCw,
    ShieldCheck,
    Sparkles,
    TicketCheck,
    UsersRound,
} from 'lucide-react';

type PriorityStatus = 'REGULAR' | 'PRIORITY';
type Direction = 'OUTBOUND' | 'RETURN';

interface EmployeeIdentity {
    employee_id: number;
    name: string;
    email: string;
    department: string | null;
    position: string | null;
    priority_status: PriorityStatus;
}

interface EmployeeSharedProps {
    auth: {
        employee: EmployeeIdentity;
    };
    [key: string]: unknown;
}

interface ScheduleSummary {
    id: number;
    direction: Direction;
    departure_time: string;
    route: {
        name: string;
        origin: string;
        destination: string;
    };
    vehicle: {
        plate_number: string;
    };
}

interface ReservationSummary {
    id: number;
    travel_date: string;
    seat_number: number;
    source: string;
    schedule: ScheduleSummary;
}

interface WaitlistSummary {
    id: number;
    travel_date: string;
    position: number;
    tier: string;
    queue_size: number;
    schedule: ScheduleSummary;
}

interface DashboardProps {
    upcomingReservations: ReservationSummary[];
    waitlists: WaitlistSummary[];
    operating_timezone?: string;
}

function displayTime(time: string): string {
    const [hourValue, minute = '00'] = time.split(':');
    const hour = Number(hourValue);
    return `${hour % 12 || 12}:${minute} ${hour >= 12 ? 'PM' : 'AM'}`;
}

function displayDate(date: string, includeWeekday = true): string {
    return new Intl.DateTimeFormat('en-PH', {
        weekday: includeWeekday ? 'short' : undefined,
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

function endpoints(schedule: ScheduleSummary): [string, string] {
    return schedule.direction === 'RETURN'
        ? [schedule.route.destination, schedule.route.origin]
        : [schedule.route.origin, schedule.route.destination];
}

export default function EmployeeDashboard({ upcomingReservations, waitlists, operating_timezone = 'Asia/Manila' }: DashboardProps) {
    const { auth } = usePage<EmployeeSharedProps>().props;
    const employee = auth.employee;
    const nextReservation = upcomingReservations[0];

    usePoll(15000, {
        only: ['upcomingReservations', 'waitlists'],
    });

    return (
        <EmployeeLayout title="Dashboard" description="Your upcoming rides and live queue updates.">
            <Head title="Employee dashboard" />
            <div className="space-y-6">
                <section className="from-brand-navy via-brand-blue-dark to-brand-blue relative overflow-hidden rounded-3xl bg-linear-to-br p-6 text-white shadow-xl shadow-blue-950/10 sm:p-8">
                    <div className="absolute -top-20 -right-16 size-56 rounded-full bg-white/10 blur-2xl" />
                    <div className="absolute -bottom-24 left-1/3 size-48 rounded-full bg-red-500/20 blur-3xl" />
                    <div className="relative z-10 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                        <div className="max-w-2xl space-y-3">
                            <Badge className="border-white/15 bg-white/10 text-blue-50 hover:bg-white/10">
                                {employee.priority_status === 'PRIORITY' ? (
                                    <ShieldCheck className="mr-1 size-3" />
                                ) : (
                                    <Sparkles className="mr-1 size-3" />
                                )}
                                {employee.priority_status === 'PRIORITY' ? 'Priority employee' : 'Employee shuttle access'}
                            </Badge>
                            <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">Welcome back, {employee.name.split(' ')[0]}.</h2>
                            <p className="max-w-xl text-sm leading-6 text-blue-100/75 sm:text-base">
                                Find your next PAGCOR shuttle, reserve a seat, and follow any waitlist updates from one place.
                            </p>
                        </div>
                        <Button asChild size="lg" className="text-brand-navy bg-white hover:bg-blue-50">
                            <Link href="/employee/schedules">
                                <CalendarDays />
                                Browse schedules
                            </Link>
                        </Button>
                    </div>
                </section>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="flex items-center gap-4 p-5">
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <TicketCheck className="size-5" />
                            </span>
                            <div>
                                <p className="text-2xl font-semibold">{upcomingReservations.length}</p>
                                <p className="text-muted-foreground text-xs">Upcoming rides</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-4 p-5">
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                <UsersRound className="size-5" />
                            </span>
                            <div>
                                <p className="text-2xl font-semibold">{waitlists.length}</p>
                                <p className="text-muted-foreground text-xs">Active waitlists</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-4 p-5">
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                {employee.priority_status === 'PRIORITY' ? <ShieldCheck className="size-5" /> : <Armchair className="size-5" />}
                            </span>
                            <div>
                                <p className="text-sm font-semibold">
                                    {employee.priority_status === 'PRIORITY' ? 'Priority access' : 'General access'}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {employee.priority_status === 'PRIORITY' ? 'Seats 1–8 enabled' : 'General seats enabled'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
                    <section>
                        <div className="mb-3 flex items-center justify-between gap-4">
                            <div>
                                <p className="text-primary text-sm font-medium">Next departure</p>
                                <h2 className="text-xl font-semibold tracking-tight">Your next shuttle</h2>
                            </div>
                            <Button asChild variant="ghost" size="sm">
                                <Link href="/employee/reservations">
                                    View all
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>

                        {nextReservation ? (
                            <NextRideCard reservation={nextReservation} timezone={operating_timezone} />
                        ) : (
                            <Card className="border-dashed">
                                <CardContent className="flex min-h-72 flex-col items-center justify-center px-6 py-10 text-center">
                                    <span className="bg-muted mb-4 flex size-14 items-center justify-center rounded-2xl">
                                        <BusFront className="text-muted-foreground size-6" />
                                    </span>
                                    <h3 className="text-lg font-semibold">No upcoming reservation</h3>
                                    <p className="text-muted-foreground mt-1 max-w-sm text-sm leading-6">
                                        Browse active shuttle schedules and choose the seat that works for you.
                                    </p>
                                    <Button asChild className="mt-5">
                                        <Link href="/employee/schedules">Reserve a shuttle</Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </section>

                    <section>
                        <div className="mb-3">
                            <p className="text-sm font-medium text-amber-700 dark:text-amber-300">Live positions</p>
                            <h2 className="text-xl font-semibold tracking-tight">Your waitlists</h2>
                        </div>
                        <Card className="min-h-72">
                            <CardContent className="p-0">
                                {waitlists.length === 0 ? (
                                    <div className="flex min-h-72 flex-col items-center justify-center px-6 py-10 text-center">
                                        <span className="bg-muted mb-3 flex size-12 items-center justify-center rounded-2xl">
                                            <UsersRound className="text-muted-foreground size-5" />
                                        </span>
                                        <p className="font-semibold">No active waitlists</p>
                                        <p className="text-muted-foreground mt-1 text-sm">Your queue positions will appear here.</p>
                                    </div>
                                ) : (
                                    <div className="divide-y">
                                        {waitlists.slice(0, 4).map((entry) => (
                                            <WaitlistRow key={entry.id} entry={entry} />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </section>
                </div>

                {upcomingReservations.length > 1 && (
                    <section>
                        <div className="mb-3 flex items-center justify-between gap-4">
                            <h2 className="text-xl font-semibold tracking-tight">Coming up after that</h2>
                            <span className="text-muted-foreground text-xs">Times shown in {operating_timezone}</span>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {upcomingReservations.slice(1, 4).map((reservation) => (
                                <CompactReservation key={reservation.id} reservation={reservation} />
                            ))}
                        </div>
                    </section>
                )}

                {employee.priority_status === 'PRIORITY' && (
                    <Alert className="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/35 dark:text-amber-100">
                        <ShieldCheck />
                        <AlertTitle>Priority access is active</AlertTitle>
                        <AlertDescription>
                            You may select any available seat, including seats 1–8, and you receive priority placement when a full shuttle’s queue is
                            promoted.
                        </AlertDescription>
                    </Alert>
                )}
            </div>
        </EmployeeLayout>
    );
}

function NextRideCard({ reservation, timezone }: { reservation: ReservationSummary; timezone: string }) {
    const [origin, destination] = endpoints(reservation.schedule);
    const autoAssigned = reservation.source === 'AUTO_ASSIGNED';

    return (
        <Card className="border-primary/20 overflow-hidden">
            <div className="bg-brand-navy flex flex-col justify-between gap-4 p-5 text-white sm:flex-row sm:items-center sm:p-6">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className="border-white/15 bg-white/10 text-white hover:bg-white/10">
                            {reservation.schedule.direction === 'OUTBOUND' ? 'Outbound' : 'Return'}
                        </Badge>
                        {autoAssigned && (
                            <Badge className="border-emerald-300/25 bg-emerald-300/15 text-emerald-100 hover:bg-emerald-300/15">
                                <RefreshCw className="mr-1 size-3" />
                                Auto-assigned
                            </Badge>
                        )}
                    </div>
                    <h3 className="mt-3 text-xl font-semibold">{reservation.schedule.route.name}</h3>
                    <p className="mt-1 text-sm text-blue-100/65">{displayDate(reservation.travel_date)}</p>
                </div>
                <div className="flex items-center gap-3 sm:text-right">
                    <span className="flex size-12 items-center justify-center rounded-2xl bg-white/10">
                        <Clock3 className="size-5" />
                    </span>
                    <div>
                        <p className="text-xl font-bold">{displayTime(reservation.schedule.departure_time)}</p>
                        <p className="text-xs text-blue-100/55">{timezone}</p>
                    </div>
                </div>
            </div>
            <CardContent className="grid gap-5 p-5 sm:grid-cols-[1fr_auto] sm:p-6">
                <div>
                    <div className="flex items-center gap-3">
                        <MapPin className="text-primary size-4 shrink-0" />
                        <p className="font-medium">{origin}</p>
                    </div>
                    <div className="border-primary/40 ml-2 h-6 border-l border-dashed" />
                    <div className="flex items-center gap-3">
                        <MapPin className="text-destructive size-4 shrink-0" />
                        <p className="font-medium">{destination}</p>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-1">
                    <div className="bg-primary/8 rounded-xl px-4 py-3">
                        <p className="text-muted-foreground text-xs">Seat</p>
                        <p className="text-primary mt-0.5 text-lg font-bold">{reservation.seat_number}</p>
                    </div>
                    <div className="bg-muted rounded-xl px-4 py-3">
                        <p className="text-muted-foreground text-xs">Vehicle</p>
                        <p className="mt-0.5 font-semibold">{reservation.schedule.vehicle.plate_number}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function WaitlistRow({ entry }: { entry: WaitlistSummary }) {
    return (
        <div className="flex items-center gap-3 p-4">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-lg font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                {entry.position}
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-semibold">{entry.schedule.route.name}</p>
                <p className="text-muted-foreground truncate text-xs">
                    {displayDate(entry.travel_date, false)} · {displayTime(entry.schedule.departure_time)}
                </p>
            </div>
            <Badge variant="outline" className="shrink-0 capitalize">
                {entry.tier.toLowerCase()}
            </Badge>
        </div>
    );
}

function CompactReservation({ reservation }: { reservation: ReservationSummary }) {
    const [, destination] = endpoints(reservation.schedule);

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 p-5 pb-3">
                <div className="min-w-0">
                    <p className="text-primary text-xs font-medium">{displayDate(reservation.travel_date)}</p>
                    <CardTitle className="mt-1 truncate text-base">{destination}</CardTitle>
                </div>
                <Badge variant="secondary">Seat {reservation.seat_number}</Badge>
            </CardHeader>
            <CardContent className="flex items-center justify-between gap-3 px-5 pb-5">
                <span className="flex items-center gap-1.5 text-sm font-medium">
                    <Clock3 className="text-muted-foreground size-4" />
                    {displayTime(reservation.schedule.departure_time)}
                </span>
                <span className="text-muted-foreground text-xs">{reservation.schedule.vehicle.plate_number}</span>
            </CardContent>
        </Card>
    );
}
