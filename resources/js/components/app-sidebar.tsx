import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, BusFront, CalendarClock, CircleHelp, ContactRound, LayoutGrid, Route, UserRound, Users } from 'lucide-react';
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
    const { auth } = usePage<SharedData>().props;
    const visibleMainNavGroups = mainNavGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => auth.user.user_type === 'ADMIN' || !item.url.startsWith('/admin/')),
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
