import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BusFront, CalendarClock, MapPinned, ShieldCheck, UsersRound } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth.user.user_type === 'ADMIN';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <section className="bg-brand-navy shadow-brand-navy/15 relative overflow-hidden rounded-3xl p-6 text-white shadow-xl md:p-10">
                    <div className="bg-brand-blue/35 absolute -top-32 -right-20 size-80 rounded-full blur-3xl" />
                    <div className="bg-brand-red/20 absolute -bottom-40 left-1/3 size-96 rounded-full blur-3xl" />
                    <div className="relative max-w-2xl">
                        <p className="mb-3 text-xs font-semibold tracking-[0.28em] text-blue-200 uppercase">PAGCOR Shuttle Operations</p>
                        <h1 className="text-3xl font-semibold tracking-tight md:text-4xl">Welcome back, {auth.user.name.split(' ')[0]}.</h1>
                        <p className="mt-3 max-w-xl text-sm leading-6 text-blue-100/75">
                            Keep every route, vehicle, driver, and reusable schedule ready for a smooth daily shuttle operation.
                        </p>
                    </div>
                </section>
                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="border-brand-blue/15 shadow-sm">
                        <CardHeader className="flex-row items-center gap-4 space-y-0">
                            <div className="bg-brand-sky text-brand-blue rounded-2xl p-3">
                                <BusFront className="size-5" />
                            </div>
                            <div>
                                <CardTitle className="text-base">Fleet readiness</CardTitle>
                                <CardDescription>Vehicles and capacity</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Link
                                href={isAdmin ? '/admin/vehicles' : '/dashboard'}
                                className="text-brand-blue inline-flex items-center gap-1 text-sm font-semibold hover:gap-2"
                            >
                                View fleet <ArrowRight className="size-4" />
                            </Link>
                        </CardContent>
                    </Card>
                    <Card className="border-brand-blue/15 shadow-sm">
                        <CardHeader className="flex-row items-center gap-4 space-y-0">
                            <div className="bg-brand-sky text-brand-blue rounded-2xl p-3">
                                <CalendarClock className="size-5" />
                            </div>
                            <div>
                                <CardTitle className="text-base">Schedule control</CardTitle>
                                <CardDescription>Reusable operating times</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Link
                                href={isAdmin ? '/admin/schedules' : '/dashboard'}
                                className="text-brand-blue inline-flex items-center gap-1 text-sm font-semibold hover:gap-2"
                            >
                                Open schedules <ArrowRight className="size-4" />
                            </Link>
                        </CardContent>
                    </Card>
                    <Card className="border-brand-blue/15 shadow-sm">
                        <CardHeader className="flex-row items-center gap-4 space-y-0">
                            <div className="bg-brand-sky text-brand-blue rounded-2xl p-3">
                                <ShieldCheck className="size-5" />
                            </div>
                            <div>
                                <CardTitle className="text-base">Access protected</CardTitle>
                                <CardDescription>Role-based workspace</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <span className="text-muted-foreground text-sm font-medium">{isAdmin ? 'Administrator access' : 'Employee access'}</span>
                        </CardContent>
                    </Card>
                </div>
                <Card className="border-brand-blue/15 shadow-sm">
                    <CardHeader>
                        <CardTitle>Quick access</CardTitle>
                        <CardDescription>Jump to the tools available in your PAGCOR workspace.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {(isAdmin
                            ? [
                                  { label: 'Manage users', href: '/admin/users', icon: UsersRound },
                                  { label: 'Manage vehicles', href: '/admin/vehicles', icon: BusFront },
                                  { label: 'Manage drivers', href: '/admin/drivers', icon: UsersRound },
                                  { label: 'Manage routes', href: '/admin/routes', icon: MapPinned },
                              ]
                            : [{ label: 'My dashboard', href: '/dashboard', icon: ShieldCheck }]
                        ).map(({ label, href, icon: Icon }) => (
                            <Button key={label} variant="outline" asChild className="border-brand-blue/15 h-auto justify-between py-4">
                                <Link href={href}>
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
