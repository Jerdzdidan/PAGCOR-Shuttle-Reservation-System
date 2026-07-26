import { Icon } from '@/components/icon';
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';

export function NavFooter({
    items,
    className,
    ...props
}: React.ComponentPropsWithoutRef<typeof SidebarGroup> & {
    items: NavItem[];
}) {
    return (
        <SidebarGroup {...props} className={`group-data-[collapsible=icon]:p-0 ${className || ''}`}>
            <SidebarGroupContent>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild className="text-sidebar-foreground/70 hover:text-sidebar-foreground">
                                {item.url.startsWith('http') ? (
                                    <a href={item.url} target="_blank" rel="noopener noreferrer">
                                        {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" />}
                                        <span>{item.title}</span>
                                    </a>
                                ) : (
                                    <Link href={item.url} prefetch>
                                        {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" />}
                                        <span>{item.title}</span>
                                    </Link>
                                )}
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
