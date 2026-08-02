import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type NavItem, type SharedData } from '@/types';
import { Link, usePage, usePoll } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    BusFront,
    CalendarCheck2,
    CalendarClock,
    CircleHelp,
    ClipboardCheck,
    ClipboardList,
    ContactRound,
    Gauge,
    LayoutGrid,
    Route,
    ScanLine,
    TriangleAlert,
    UserRound,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavGroups: NavGroup[] = [
    {
        title: 'Main',
        items: [
            {
                title: 'Dashboard',
                url: '/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Schedules',
                url: '/admin/schedules',
                icon: CalendarClock,
            },
            {
                title: 'Finished Services',
                url: '/admin/finished-services',
                icon: CalendarCheck2,
            },
        ],
    },
    {
        title: 'User Management',
        items: [
            {
                title: 'Users',
                url: '/admin/users',
                icon: Users,
            },
            {
                title: 'Employees',
                url: '/admin/employees',
                icon: ContactRound,
            },
            {
                title: 'Departments',
                url: '/admin/departments',
                icon: Building2,
            },
        ],
    },
    {
        title: 'Utilities',
        items: [
            {
                title: 'Vehicles',
                url: '/admin/vehicles',
                icon: BusFront,
            },
            {
                title: 'Routes',
                url: '/admin/routes',
                icon: Route,
            },
            {
                title: 'Drivers',
                url: '/admin/drivers',
                icon: UserRound,
            },
        ],
    },
    {
        title: 'Reports',
        items: [
            {
                title: 'Service Completion',
                url: '/admin/reports/service-completion',
                icon: ClipboardCheck,
            },
            {
                title: 'Fleet Utilization',
                url: '/admin/reports/fleet-utilization',
                icon: BarChart3,
            },
            {
                title: 'Route & Schedule Demand',
                url: '/admin/reports/route-schedule-demand',
                icon: Route,
            },
            {
                title: 'Shuttle Attendance',
                url: '/admin/reports/shuttle-attendance',
                icon: ClipboardList,
            },
            {
                title: 'Driver Utilization',
                url: '/admin/reports/driver-utilization',
                icon: Gauge,
            },
            {
                title: 'Login Activity',
                url: '/admin/reports/login-activity',
                icon: ScanLine,
            },
            {
                title: 'Incident Log',
                url: '/admin/reports/incident-log',
                icon: TriangleAlert,
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'PAGCOR website',
        url: 'https://www.pagcor.ph',
        icon: Building2,
    },
    {
        title: 'Operations guide',
        url: '/dashboard',
        icon: CircleHelp,
    },
];

export function AppSidebar() {
    const { auth, pending_completion_count = 0 } = usePage<SharedData>().props;

    usePoll(30000, {
        only: ['pending_completion_count'],
    });

    const visibleMainNavGroups = mainNavGroups
        .map((group) => ({
            ...group,
            items: group.items
                .filter((item) => auth.user.user_type === 'ADMIN' || !item.url.startsWith('/admin/'))
                .map((item) =>
                    item.url === '/admin/finished-services'
                        ? {
                              ...item,
                              badge: pending_completion_count > 0 ? pending_completion_count : null,
                          }
                        : item,
                ),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="border-sidebar-border/80 from-sidebar to-brand-navy/90 border-b bg-gradient-to-b p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="pt-3">
                <NavMain groups={visibleMainNavGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
