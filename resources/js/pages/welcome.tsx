import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BusFront, CalendarClock, MapPinned, ShieldCheck, UsersRound } from 'lucide-react';

const features = [
    { icon: BusFront, title: 'Fleet management', description: 'Keep vehicle capacity, status, and notes current.' },
    { icon: MapPinned, title: 'Route control', description: 'Maintain a clean endpoint-based route catalog.' },
    { icon: CalendarClock, title: 'Reusable schedules', description: 'Publish dependable operating times for the team.' },
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome">
                <meta name="description" content="PAGCOR Shuttle Reservation System" />
            </Head>
            <div className="bg-brand-sky text-brand-navy min-h-svh overflow-hidden">
                <header className="mx-auto flex w-full max-w-7xl items-center justify-between gap-6 px-6 py-5 lg:px-10">
                    <Link href={route('home')} className="flex items-center gap-3">
                        <span className="rounded-xl bg-white p-1 shadow-sm">
                            <AppLogoIcon className="size-11 object-contain" />
                        </span>
                        <span className="hidden text-left sm:block">
                            <span className="text-brand-blue block text-xs font-semibold tracking-[0.22em]">PAGCOR</span>
                            <span className="block text-sm font-semibold">Shuttle Reservation System</span>
                        </span>
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={route('dashboard')}>
                                    Open dashboard <ArrowRight />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={route('login')}>Administrator login</Link>
                                </Button>
                                <Button asChild>
                                    <Link href="/employee/login">
                                        Employee QR login <ArrowRight />
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>
                <main className="mx-auto grid w-full max-w-7xl gap-14 px-6 pt-10 pb-16 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:px-10 lg:pt-20 lg:pb-24">
                    <section>
                        <div className="border-brand-blue/15 text-brand-blue mb-6 inline-flex items-center gap-2 rounded-full border bg-white/70 px-3 py-1.5 text-xs font-semibold tracking-[0.2em] uppercase">
                            <ShieldCheck className="size-4" /> Trusted operations workspace
                        </div>
                        <h1 className="max-w-3xl text-5xl leading-[1.05] font-semibold tracking-tight md:text-6xl">
                            Move people with <span className="text-brand-blue">confidence.</span>
                        </h1>
                        <p className="text-brand-navy/65 mt-6 max-w-xl text-lg leading-8">
                            PAGCOR Shuttle Reservation System brings fleet configuration, driver assignments, routes, and schedules into one clear,
                            secure place.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            {auth.user ? (
                                <Button size="lg" asChild>
                                    <Link href={route('dashboard')}>
                                        Go to workspace <ArrowRight />
                                    </Link>
                                </Button>
                            ) : (
                                <Button size="lg" asChild>
                                    <Link href={route('login')}>
                                        Access the system <ArrowRight />
                                    </Link>
                                </Button>
                            )}
                            <Button size="lg" variant="outline" className="border-brand-blue/20 bg-white/60" asChild>
                                <a href="#capabilities">Explore capabilities</a>
                            </Button>
                        </div>
                    </section>
                    <section className="relative">
                        <div className="bg-brand-blue/15 absolute inset-6 rounded-[2.5rem] blur-3xl" />
                        <div className="shadow-brand-navy/10 relative overflow-hidden rounded-[2rem] border border-white/80 bg-white/80 p-8 shadow-2xl backdrop-blur md:p-10">
                            <div className="from-brand-red via-brand-blue to-brand-blue-dark absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r" />
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-brand-blue text-xs font-semibold tracking-[0.25em] uppercase">Daily readiness</p>
                                    <h2 className="mt-2 text-2xl font-semibold">One connected view.</h2>
                                </div>
                                <div className="bg-brand-navy rounded-2xl p-3 text-white">
                                    <BusFront className="size-7" />
                                </div>
                            </div>
                            <div className="mt-8 space-y-4">
                                {[
                                    ['Fleet', 'Configured and capacity-aware'],
                                    ['Drivers', 'Assigned with compliance in view'],
                                    ['Schedules', 'Ready for operating days'],
                                ].map(([label, detail], index) => (
                                    <div key={label} className="border-brand-blue/10 flex items-center gap-4 rounded-2xl border bg-white/80 p-4">
                                        <div
                                            className={`flex size-10 items-center justify-center rounded-xl text-sm font-bold text-white ${index === 1 ? 'bg-brand-red' : 'bg-brand-blue'}`}
                                        >
                                            {index + 1}
                                        </div>
                                        <div>
                                            <p className="font-semibold">{label}</p>
                                            <p className="text-muted-foreground text-sm">{detail}</p>
                                        </div>
                                        <span className="ml-auto size-2.5 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]" />
                                    </div>
                                ))}
                            </div>
                            <div className="bg-brand-navy mt-8 rounded-2xl p-5 text-white">
                                <div className="flex items-center gap-3">
                                    <UsersRound className="size-5 text-blue-200" />
                                    <p className="text-sm font-medium text-blue-100">Designed for PAGCOR teams</p>
                                </div>
                                <p className="mt-2 text-sm leading-6 text-blue-100/65">
                                    Clear ownership, controlled access, and a dependable operational foundation.
                                </p>
                            </div>
                        </div>
                    </section>
                </main>
                <section id="capabilities" className="border-brand-blue/10 border-t bg-white/60">
                    <div className="mx-auto w-full max-w-7xl px-6 py-14 lg:px-10">
                        <div className="mb-8 flex flex-col justify-between gap-3 md:flex-row md:items-end">
                            <div>
                                <p className="text-brand-red text-xs font-semibold tracking-[0.25em] uppercase">Built around the operation</p>
                                <h2 className="mt-2 text-3xl font-semibold tracking-tight">Everything your admin team needs to stay ready.</h2>
                            </div>
                            <p className="text-muted-foreground max-w-md text-sm leading-6">
                                A focused foundation for the next phases of reservation, attendance, monitoring, and reporting.
                            </p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            {features.map(({ icon: Icon, title, description }) => (
                                <div key={title} className="border-brand-blue/10 rounded-2xl border bg-white p-6 shadow-sm">
                                    <div className="bg-brand-sky text-brand-blue mb-5 flex size-11 items-center justify-center rounded-xl">
                                        <Icon className="size-5" />
                                    </div>
                                    <h3 className="font-semibold">{title}</h3>
                                    <p className="text-muted-foreground mt-2 text-sm leading-6">{description}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
                <footer className="text-brand-navy/50 mx-auto flex w-full max-w-7xl items-center gap-2 px-6 py-6 text-xs font-medium lg:px-10">
                    <span className="bg-brand-red size-2 rounded-full" /> PAGCOR Shuttle Reservation System
                </footer>
            </div>
        </>
    );
}
