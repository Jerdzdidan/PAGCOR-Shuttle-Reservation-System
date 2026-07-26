import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/react';

function urlPath(url: string): string {
    return url.split(/[?#]/, 1)[0] || '/';
}

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const page = usePage();
    const currentPath = urlPath(page.url);

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-1">
                    <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={currentPath === urlPath(item.url) || currentPath.startsWith(`${urlPath(item.url)}/`)}
                                >
                                    <Link href={item.url} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
