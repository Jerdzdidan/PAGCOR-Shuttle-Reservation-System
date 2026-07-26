import AppLogoIcon from '@/components/app-logo-icon';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useStoredAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, LayoutDashboard, LogOut, ShieldCheck, TicketCheck } from 'lucide-react';
import { type ReactNode } from 'react';

interface EmployeeIdentity {
    employee_id: number;
    name: string;
    email: string;
    department: string | null;
    position: string | null;
    priority_status: 'REGULAR' | 'PRIORITY';
}

interface EmployeeSharedProps {
    auth: {
        employee: EmployeeIdentity | null;
    };
    [key: string]: unknown;
}

interface EmployeeLayoutProps {
    children: ReactNode;
    title: string;
    description?: string;
}

const navigation = [
    { label: 'Dashboard', href: '/employee/dashboard', icon: LayoutDashboard },
    { label: 'Schedules', href: '/employee/schedules', icon: CalendarDays },
    { label: 'My reservations', href: '/employee/reservations', icon: TicketCheck },
];

function employeeInitials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

export default function EmployeeLayout({ children, title, description }: EmployeeLayoutProps) {
    useStoredAppearance();

    const page = usePage<EmployeeSharedProps>();
    const employee = page.props.auth.employee;
    const currentPath = page.url.split('?')[0];

    if (!employee) {
        return null;
    }

    return (
        <div className="bg-background min-h-svh">
            <aside className="bg-brand-navy fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-white/10 text-white lg:flex">
                <div className="flex h-20 items-center gap-3 border-b border-white/10 px-6">
                    <span className="rounded-xl bg-white p-1.5 shadow-sm">
                        <AppLogoIcon className="size-10 object-contain" />
                    </span>
                    <span className="min-w-0">
                        <span className="block text-xs font-semibold tracking-[0.2em] text-blue-200">PAGCOR</span>
                        <span className="block truncate text-sm font-semibold">Employee Shuttle Portal</span>
                    </span>
                </div>

                <nav className="flex flex-1 flex-col gap-2 p-4" aria-label="Employee navigation">
                    <p className="px-3 py-2 text-[0.65rem] font-semibold tracking-[0.18em] text-blue-200/70 uppercase">Reservation</p>
                    {navigation.map((item) => {
                        const isActive = currentPath === item.href;
                        const Icon = item.icon;

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                prefetch
                                className={cn(
                                    'flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition-colors',
                                    isActive ? 'text-brand-navy bg-white shadow-sm' : 'text-blue-100/80 hover:bg-white/10 hover:text-white',
                                )}
                            >
                                <Icon className="size-5" />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-t border-white/10 p-4">
                    <div className="rounded-2xl bg-white/7 p-4 ring-1 ring-white/10">
                        <div className="flex items-center gap-3">
                            <Avatar className="size-10 border border-white/15">
                                <AvatarFallback className="bg-brand-blue text-sm font-semibold text-white">
                                    {employeeInitials(employee.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold">{employee.name}</p>
                                <p className="truncate text-xs text-blue-100/60">{employee.department ?? employee.email}</p>
                            </div>
                        </div>
                        <div className="mt-3 flex items-center justify-between gap-2">
                            <Badge
                                className={cn(
                                    'border-0',
                                    employee.priority_status === 'PRIORITY'
                                        ? 'bg-amber-300 text-amber-950 hover:bg-amber-300'
                                        : 'bg-white/10 text-blue-100 hover:bg-white/10',
                                )}
                            >
                                {employee.priority_status === 'PRIORITY' && <ShieldCheck className="mr-1 size-3" />}
                                {employee.priority_status}
                            </Badge>
                            <Link
                                href="/employee/logout"
                                method="post"
                                as="button"
                                className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs text-blue-100/70 transition-colors hover:bg-white/10 hover:text-white"
                            >
                                <LogOut className="size-3.5" />
                                Logout
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>

            <div className="lg:pl-72">
                <header className="bg-background/92 sticky top-0 z-30 border-b backdrop-blur-xl">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-20 lg:px-8">
                        <Link href="/employee/dashboard" className="flex items-center gap-2.5 lg:hidden">
                            <span className="ring-border rounded-lg bg-white p-1 shadow-sm ring-1">
                                <AppLogoIcon className="size-8 object-contain" />
                            </span>
                            <span>
                                <span className="text-brand-blue block text-[0.65rem] font-bold tracking-[0.18em]">PAGCOR</span>
                                <span className="text-brand-navy block text-xs font-semibold dark:text-blue-100">Employee Portal</span>
                            </span>
                        </Link>

                        <div className="hidden min-w-0 lg:block">
                            <h1 className="truncate text-xl font-semibold tracking-tight">{title}</h1>
                            {description && <p className="text-muted-foreground truncate text-sm">{description}</p>}
                        </div>

                        <div className="flex items-center gap-2">
                            <div className="hidden text-right sm:block lg:hidden">
                                <p className="max-w-40 truncate text-sm font-semibold">{employee.name}</p>
                                <p className="text-muted-foreground text-xs">ID {employee.employee_id}</p>
                            </div>
                            {employee.priority_status === 'PRIORITY' && (
                                <Badge className="hidden border-amber-300 bg-amber-100 text-amber-900 hover:bg-amber-100 sm:inline-flex dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                    <ShieldCheck className="mr-1 size-3" />
                                    Priority
                                </Badge>
                            )}
                            <AppearanceToggleDropdown />
                            <Button asChild variant="ghost" size="icon" className="lg:hidden">
                                <Link href="/employee/logout" method="post" as="button">
                                    <LogOut />
                                    <span className="sr-only">Log out</span>
                                </Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto min-h-[calc(100svh-5rem)] max-w-7xl px-4 py-6 pb-28 sm:px-6 lg:px-8 lg:py-8 lg:pb-8">
                    <div className="mb-6 lg:hidden">
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                        {description && <p className="text-muted-foreground mt-1 text-sm">{description}</p>}
                    </div>
                    {children}
                </main>
            </div>

            <nav
                className="bg-background/95 fixed right-3 bottom-3 left-3 z-40 grid grid-cols-3 rounded-2xl border p-1.5 shadow-xl backdrop-blur-xl lg:hidden"
                aria-label="Employee navigation"
            >
                {navigation.map((item) => {
                    const isActive = currentPath === item.href;
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            prefetch
                            className={cn(
                                'flex min-w-0 flex-col items-center gap-1 rounded-xl px-2 py-2 text-[0.68rem] font-medium transition-colors',
                                isActive
                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                            )}
                        >
                            <Icon className="size-5" />
                            <span className="truncate">{item.label}</span>
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
