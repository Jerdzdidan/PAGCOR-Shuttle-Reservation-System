import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { AlertTriangle, ArrowDownToLine, ArrowRight, BusFront, CalendarDays, Clock3, MapPinned, UserRound, UsersRound } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface DailySummary {
    date: string;
    generated_at: string;
    timezone: string;
    trips: {
        total: number;
        scheduled: number;
        awaiting_completion: number;
        completed: number;
        not_operated: number;
    };
    employees: {
        time_in: number;
    };
    passengers: {
        boarded: number;
        reserved: number;
        no_shows: number;
    };
}

interface DashboardProps {
    dailySummary: DailySummary;
}

function displayDate(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        timeZone: timezone,
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00+08:00`));
}

function displayTime(value: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-PH', {
        timeZone: timezone,
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function TripCount({ label, value, tone }: { label: string; value: number; tone: string }) {
    return (
        <div className="bg-muted/55 rounded-xl p-3">
            <div className="flex items-center gap-2">
                <span className={`size-2 rounded-full ${tone}`} />
                <span className="text-muted-foreground text-xs">{label}</span>
            </div>
            <p className="mt-1 text-lg font-semibold tabular-nums">{value.toLocaleString()}</p>
        </div>
    );
}

export default function Dashboard({ dailySummary }: DashboardProps) {
    const { auth, pending_completion_count = 0 } = usePage<SharedData>().props;

    usePoll(30000, {
        only: ['dailySummary', 'pending_completion_count'],
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="bg-brand-navy shadow-brand-navy/15 relative min-w-0 overflow-hidden rounded-3xl p-6 text-white shadow-xl md:p-8">
                    <div className="bg-brand-blue/35 absolute -top-32 -right-20 size-80 rounded-full blur-3xl" />
                    <div className="bg-brand-red/20 absolute -bottom-40 left-1/3 size-96 rounded-full blur-3xl" />
                    <div className="relative">
                        <p className="text-xs font-semibold tracking-[0.22em] text-blue-200 uppercase">PAGCOR Shuttle Operations</p>
                        <h1 className="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">Welcome back, {auth.user.name.split(' ')[0]}.</h1>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-blue-100/75">Here is today&apos;s shuttle activity at a glance.</p>
                        <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-blue-100/70">
                            <span>{displayDate(dailySummary.date, dailySummary.timezone)}</span>
                            <span className="inline-flex items-center gap-1.5">
                                <Clock3 className="size-3.5" />
                                Updated {displayTime(dailySummary.generated_at, dailySummary.timezone)}
                            </span>
                        </div>
                    </div>
                </section>

                <section aria-label="Daily shuttle summary" className="grid min-w-0 gap-4 lg:grid-cols-3">
                    <Card className="min-w-0 overflow-hidden border-blue-200/70 shadow-sm dark:border-blue-900">
                        <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle className="text-base">Daily trips</CardTitle>
                                <CardDescription className="mt-1">All shuttle services scheduled today.</CardDescription>
                            </div>
                            <span className="bg-brand-sky text-brand-blue flex size-11 shrink-0 items-center justify-center rounded-2xl dark:bg-blue-950 dark:text-blue-300">
                                <BusFront className="size-5" />
                            </span>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div>
                                <p className="text-4xl font-semibold tracking-tight tabular-nums">{dailySummary.trips.total.toLocaleString()}</p>
                                <p className="text-muted-foreground mt-1 text-xs">Total trips</p>
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <TripCount label="Scheduled" value={dailySummary.trips.scheduled} tone="bg-blue-500" />
                                <TripCount label="Completed" value={dailySummary.trips.completed} tone="bg-emerald-500" />
                                <TripCount label="Needs completion" value={dailySummary.trips.awaiting_completion} tone="bg-amber-500" />
                                <TripCount label="Not operated" value={dailySummary.trips.not_operated} tone="bg-slate-500" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="min-w-0 overflow-hidden border-emerald-200/70 shadow-sm dark:border-emerald-900">
                        <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle className="text-base">Employee time-ins</CardTitle>
                                <CardDescription className="mt-1">Employees recorded as boarded today.</CardDescription>
                            </div>
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <CalendarDays className="size-5" />
                            </span>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-2xl bg-emerald-50 p-4 dark:bg-emerald-950/45">
                                <ArrowDownToLine className="size-5 text-emerald-700 dark:text-emerald-300" />
                                <p className="mt-4 text-3xl font-semibold tabular-nums">{dailySummary.employees.time_in.toLocaleString()}</p>
                                <p className="mt-1 text-sm font-medium">Time in</p>
                                <p className="text-muted-foreground mt-1 text-xs">All recorded shuttle boardings</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="min-w-0 overflow-hidden border-violet-200/70 shadow-sm dark:border-violet-900">
                        <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle className="text-base">Number of passengers</CardTitle>
                                <CardDescription className="mt-1">Passenger totals across today&apos;s trips.</CardDescription>
                            </div>
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                <UsersRound className="size-5" />
                            </span>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div>
                                <p className="text-4xl font-semibold tracking-tight tabular-nums">
                                    {dailySummary.passengers.boarded.toLocaleString()}
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">Passengers boarded</p>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="bg-muted/55 rounded-xl p-4">
                                    <p className="text-muted-foreground text-xs">Reserved</p>
                                    <p className="mt-1 text-xl font-semibold tabular-nums">{dailySummary.passengers.reserved.toLocaleString()}</p>
                                </div>
                                <div className="bg-muted/55 rounded-xl p-4">
                                    <p className="text-muted-foreground text-xs">No-shows</p>
                                    <p className="mt-1 text-xl font-semibold tabular-nums">{dailySummary.passengers.no_shows.toLocaleString()}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>

                {dailySummary.trips.total === 0 && (
                    <div className="border-border bg-card text-muted-foreground flex items-center gap-3 rounded-xl border p-4 text-sm shadow-sm">
                        <CalendarDays className="size-4 shrink-0" />
                        No shuttle trips are recorded for today.
                    </div>
                )}

                {pending_completion_count > 0 && (
                    <Alert className="border-amber-200 bg-amber-50/80 text-amber-950 shadow-sm dark:border-amber-900 dark:bg-amber-950/35 dark:text-amber-100">
                        <AlertTriangle className="text-amber-700 dark:text-amber-300" />
                        <AlertTitle>Trip closeout is waiting</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>
                                {pending_completion_count} departed {pending_completion_count === 1 ? 'service still needs' : 'services still need'}{' '}
                                attendance and odometer completion.
                            </span>
                            <Button variant="outline" size="sm" asChild className="border-amber-300 bg-white/70 sm:shrink-0 dark:bg-amber-950/50">
                                <Link href="/admin/finished-services?view=needs_completion">
                                    Review services
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                <Card className="border-brand-blue/15 shadow-sm">
                    <CardHeader>
                        <CardTitle>Quick access</CardTitle>
                        <CardDescription>Jump to the management tools used in daily shuttle operations.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            { label: 'Manage users', href: '/admin/users', icon: UsersRound },
                            { label: 'Manage vehicles', href: '/admin/vehicles', icon: BusFront },
                            { label: 'Manage drivers', href: '/admin/drivers', icon: UserRound },
                            { label: 'Manage routes', href: '/admin/routes', icon: MapPinned },
                        ].map(({ label, href, icon: Icon }) => (
                            <Button key={label} variant="outline" asChild className="border-brand-blue/15 h-auto justify-between py-4">
                                <Link href={href} prefetch>
                                    <span className="flex items-center gap-3">
                                        <Icon className="text-brand-blue size-4" />
                                        {label}
                                    </span>
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
