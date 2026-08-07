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
    LayoutGrid,
    LayoutList,
    Route,
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
                title: 'All reports',
                url: '/admin/reports',
                icon: LayoutList,
            },
            {
                title: 'Service Delivery',
                url: '/admin/reports/service-delivery',
                icon: ClipboardCheck,
            },
            {
                title: 'Schedule Attendance',
                url: '/admin/reports/schedule-attendance',
                icon: ClipboardList,
            },
            {
                title: 'Route Demand',
                url: '/admin/reports/route-demand',
                icon: BarChart3,
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
            <SidebarHeader className="border-sidebar-border/60 from-sidebar to-brand-navy/90 border-b bg-gradient-to-b p-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="hover:bg-sidebar-accent/60">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="[&::-webkit-scrollbar-thumb]:bg-sidebar-foreground/15 hover:[&::-webkit-scrollbar-thumb]:bg-sidebar-foreground/30 gap-1 py-3 [scrollbar-color:var(--color-sidebar-border)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:transition-colors [&::-webkit-scrollbar-track]:bg-transparent">
                <NavMain groups={visibleMainNavGroups} />
            </SidebarContent>

            <SidebarFooter className="border-sidebar-border/60 mt-auto gap-1 border-t pt-2">
                <NavFooter items={footerNavItems} />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
