import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import EmployeeLayout from '@/layouts/employee-layout';
import { cn } from '@/lib/utils';
import { Head, Link, useForm, usePage, usePoll } from '@inertiajs/react';
import {
    Armchair,
    ArrowRight,
    BusFront,
    CalendarDays,
    Clock3,
    LoaderCircle,
    MapPin,
    RefreshCw,
    ShieldCheck,
    TicketCheck,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type Direction = 'OUTBOUND' | 'RETURN';
type ViewFilter = 'ALL' | 'CONFIRMED' | 'WAITLIST';

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

interface ReservationsPageProps {
    reservations: ReservationSummary[];
    waitlists: WaitlistSummary[];
    operating_timezone?: string;
}

interface CancelTarget {
    endpoint: string;
    title: string;
    description: string;
    successMessage: string;
}

interface BookingWindowProps {
    booking_window: { enabled: boolean; is_open: boolean; message: string | null } | null;
    [key: string]: unknown;
}

function displayDate(date: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

function displayTime(time: string): string {
    const [hourValue, minute = '00'] = time.split(':');
    const hour = Number(hourValue);
    return `${hour % 12 || 12}:${minute} ${hour >= 12 ? 'PM' : 'AM'}`;
}

function endpoints(schedule: ScheduleSummary): [string, string] {
    return schedule.direction === 'RETURN'
        ? [schedule.route.destination, schedule.route.origin]
        : [schedule.route.origin, schedule.route.destination];
}

export default function EmployeeReservations({ reservations, waitlists, operating_timezone = 'Asia/Manila' }: ReservationsPageProps) {
    const [view, setView] = useState<ViewFilter>('ALL');
    const [cancelTarget, setCancelTarget] = useState<CancelTarget | null>(null);
    const cancelForm = useForm({});
    const bookingWindow = usePage<BookingWindowProps>().props.booking_window;
    const bookingLocked = bookingWindow !== null && bookingWindow.enabled && !bookingWindow.is_open;

    usePoll(10000, {
        only: ['reservations', 'waitlists'],
    });

    function cancel(): void {
        if (!cancelTarget) {
            return;
        }

        cancelForm.delete(cancelTarget.endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(cancelTarget.successMessage);
                setCancelTarget(null);
            },
            onError: (errors) => {
                const message = Object.values(errors)[0];
                toast.error(typeof message === 'string' ? message : 'This request could not be completed.');
            },
        });
    }

    const showReservations = view === 'ALL' || view === 'CONFIRMED';
    const showWaitlists = view === 'ALL' || view === 'WAITLIST';
    const hasVisibleItems = (showReservations && reservations.length > 0) || (showWaitlists && waitlists.length > 0);

    return (
        <EmployeeLayout title="My reservations" description="Review confirmed seats and follow your waitlist positions.">
            <Head title="My shuttle reservations" />
            <div className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Card className="overflow-hidden">
                        <CardContent className="flex items-center justify-between gap-4 p-5 sm:p-6">
                            <div>
                                <p className="text-muted-foreground text-sm">Confirmed seats</p>
                                <p className="mt-1 text-3xl font-semibold">{reservations.length}</p>
                            </div>
                            <span className="flex size-13 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <TicketCheck className="size-6" />
                            </span>
                        </CardContent>
                    </Card>
                    <Card className="overflow-hidden">
                        <CardContent className="flex items-center justify-between gap-4 p-5 sm:p-6">
                            <div>
                                <p className="text-muted-foreground text-sm">Active waitlists</p>
                                <p className="mt-1 text-3xl font-semibold">{waitlists.length}</p>
                            </div>
                            <span className="flex size-13 items-center justify-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                <UsersRound className="size-6" />
                            </span>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h2 className="text-2xl font-semibold tracking-tight">Your upcoming travel</h2>
                        <p className="text-muted-foreground text-sm">Live reservation data · {operating_timezone}</p>
                    </div>
                    <div className="bg-muted grid grid-cols-3 rounded-xl p-1">
                        {[
                            { value: 'ALL' as const, label: 'All' },
                            { value: 'CONFIRMED' as const, label: 'Confirmed' },
                            { value: 'WAITLIST' as const, label: 'Waitlist' },
                        ].map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                onClick={() => setView(item.value)}
                                className={cn(
                                    'rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:text-sm',
                                    view === item.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                {!hasVisibleItems ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center px-6 py-16 text-center">
                            <span className="bg-muted mb-4 flex size-14 items-center justify-center rounded-2xl">
                                <BusFront className="text-muted-foreground size-6" />
                            </span>
                            <h2 className="text-lg font-semibold">
                                {view === 'CONFIRMED'
                                    ? 'No confirmed reservations'
                                    : view === 'WAITLIST'
                                      ? 'No active waitlists'
                                      : 'Nothing booked yet'}
                            </h2>
                            <p className="text-muted-foreground mt-1 max-w-sm text-sm leading-6">
                                {bookingLocked ? bookingWindow?.message : 'Choose an available schedule and select your preferred shuttle seat.'}
                            </p>
                            {!bookingLocked && (
                                <Button asChild className="mt-5">
                                    <Link href="/employee/schedules">
                                        <CalendarDays />
                                        Browse schedules
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-7">
                        {showReservations && reservations.length > 0 && (
                            <section className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <TicketCheck className="size-5 text-emerald-600 dark:text-emerald-300" />
                                    <h3 className="text-lg font-semibold">Confirmed reservations</h3>
                                </div>
                                <div className="grid gap-4 lg:grid-cols-2">
                                    {reservations.map((reservation) => (
                                        <ReservationCard
                                            key={reservation.id}
                                            reservation={reservation}
                                            onCancel={() =>
                                                setCancelTarget({
                                                    endpoint: `/employee/reservations/${reservation.id}`,
                                                    title: 'Cancel this reservation?',
                                                    description: `Seat ${reservation.seat_number} will be released. If this shuttle has a queue, the next eligible employee may receive it automatically.`,
                                                    successMessage: 'Your reservation was cancelled.',
                                                })
                                            }
                                        />
                                    ))}
                                </div>
                            </section>
                        )}

                        {showWaitlists && waitlists.length > 0 && (
                            <section className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <UsersRound className="size-5 text-amber-600 dark:text-amber-300" />
                                    <h3 className="text-lg font-semibold">Active waitlists</h3>
                                </div>
                                <div className="grid gap-4 lg:grid-cols-2">
                                    {waitlists.map((entry) => (
                                        <WaitlistCard
                                            key={entry.id}
                                            entry={entry}
                                            onCancel={() =>
                                                setCancelTarget({
                                                    endpoint: `/employee/waitlist/${entry.id}`,
                                                    title: 'Leave this waitlist?',
                                                    description: 'Leaving now removes your current priority and FCFS position for this departure.',
                                                    successMessage: 'You left the waitlist.',
                                                })
                                            }
                                        />
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>
                )}

                <Alert>
                    <RefreshCw />
                    <AlertTitle>Automatic seat assignment</AlertTitle>
                    <AlertDescription>
                        When a reserved seat is cancelled, the system assigns it to the next eligible priority employee first, then the next regular
                        employee by first-come, first-served order.
                    </AlertDescription>
                </Alert>
            </div>

            <Dialog open={cancelTarget !== null} onOpenChange={(open) => !open && !cancelForm.processing && setCancelTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{cancelTarget?.title}</DialogTitle>
                        <DialogDescription>{cancelTarget?.description}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setCancelTarget(null)} disabled={cancelForm.processing}>
                            Keep it
                        </Button>
                        <Button type="button" variant="destructive" onClick={cancel} disabled={cancelForm.processing}>
                            {cancelForm.processing && <LoaderCircle className="animate-spin" />}
                            Confirm cancellation
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </EmployeeLayout>
    );
}

function ReservationCard({ reservation, onCancel }: { reservation: ReservationSummary; onCancel: () => void }) {
    const [origin, destination] = endpoints(reservation.schedule);
    const autoAssigned = reservation.source === 'AUTO_ASSIGNED';

    return (
        <Card className="overflow-hidden">
            <div className="h-1 bg-emerald-500" />
            <CardContent className="space-y-5 p-5 sm:p-6">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className="border-emerald-200 bg-emerald-100 text-emerald-900 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                                Confirmed
                            </Badge>
                            {autoAssigned && (
                                <Badge variant="outline">
                                    <RefreshCw className="mr-1 size-3" />
                                    Auto-assigned
                                </Badge>
                            )}
                        </div>
                        <h4 className="mt-2 truncate text-lg font-semibold">{reservation.schedule.route.name}</h4>
                        <p className="text-muted-foreground text-sm">{displayDate(reservation.travel_date)}</p>
                    </div>
                    <div className="bg-primary/8 flex items-center gap-2 rounded-xl px-3 py-2">
                        <Clock3 className="text-primary size-4" />
                        <span className="text-primary font-semibold">{displayTime(reservation.schedule.departure_time)}</span>
                    </div>
                </div>

                <div className="bg-muted/20 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 rounded-xl border p-4 text-sm">
                    <MapPin className="text-primary mt-0.5 size-4" />
                    <p className="font-medium">{origin}</p>
                    <span className="border-primary/40 mx-auto h-5 border-l border-dashed" />
                    <span />
                    <MapPin className="text-destructive mt-0.5 size-4" />
                    <p className="font-medium">{destination}</p>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div className="bg-muted rounded-xl p-3">
                        <p className="text-muted-foreground text-xs">Assigned seat</p>
                        <p className="mt-1 flex items-center gap-2 text-lg font-bold">
                            <Armchair className="text-primary size-4" />
                            {reservation.seat_number}
                        </p>
                    </div>
                    <div className="bg-muted rounded-xl p-3">
                        <p className="text-muted-foreground text-xs">Vehicle</p>
                        <p className="mt-1 font-semibold">{reservation.schedule.vehicle.plate_number}</p>
                    </div>
                </div>

                <Button type="button" variant="outline" className="w-full" onClick={onCancel}>
                    Cancel reservation
                </Button>
            </CardContent>
        </Card>
    );
}

function WaitlistCard({ entry, onCancel }: { entry: WaitlistSummary; onCancel: () => void }) {
    const [origin, destination] = endpoints(entry.schedule);

    return (
        <Card className="overflow-hidden">
            <div className="h-1 bg-amber-500" />
            <CardContent className="space-y-5 p-5 sm:p-6">
                <div className="flex items-start gap-4">
                    <span className="flex size-14 shrink-0 flex-col items-center justify-center rounded-2xl bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                        <span className="text-[0.6rem] font-bold tracking-wider uppercase">Position</span>
                        <span className="text-xl font-bold">{entry.position}</span>
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className="border-amber-200 bg-amber-100 text-amber-900 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                Waitlisted
                            </Badge>
                            <Badge variant="outline" className="capitalize">
                                {entry.tier === 'PRIORITY' && <ShieldCheck className="mr-1 size-3" />}
                                {entry.tier.toLowerCase()} tier
                            </Badge>
                        </div>
                        <h4 className="mt-2 truncate text-lg font-semibold">{entry.schedule.route.name}</h4>
                        <p className="text-muted-foreground text-sm">
                            {displayDate(entry.travel_date)} · {displayTime(entry.schedule.departure_time)}
                        </p>
                    </div>
                </div>

                <div className="bg-muted/20 rounded-xl border p-4">
                    <div className="flex items-center gap-2 text-sm">
                        <MapPin className="text-primary size-4 shrink-0" />
                        <span className="truncate font-medium">{origin}</span>
                        <ArrowRight className="text-muted-foreground size-4 shrink-0" />
                        <span className="truncate font-medium">{destination}</span>
                    </div>
                    <div className="text-muted-foreground mt-3 flex items-center justify-between gap-3 border-t pt-3 text-xs">
                        <span>{entry.queue_size} total in queue</span>
                        <span>{entry.schedule.vehicle.plate_number}</span>
                    </div>
                </div>

                <p className="text-muted-foreground text-sm leading-6">
                    Your position refreshes automatically. You will receive the exact cancelled seat if you are next and eligible.
                </p>

                <Button type="button" variant="outline" className="w-full" onClick={onCancel}>
                    Leave waitlist
                </Button>
            </CardContent>
        </Card>
    );
}
