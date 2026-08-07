import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

function urlPath(url: string): string {
    return url.split(/[?#]/, 1)[0] || '/';
}

function isCurrent(currentPath: string, url: string): boolean {
    const path = urlPath(url);

    return currentPath === path || currentPath.startsWith(`${path}/`);
}

function NavLinks({ items, currentPath }: { items: NavItem[]; currentPath: string }) {
    return (
        <SidebarMenu className="gap-0.5">
            {items.map((item) => (
                <SidebarMenuItem key={item.title}>
                    <SidebarMenuButton
                        asChild
                        tooltip={item.title}
                        isActive={isCurrent(currentPath, item.url)}
                        className="text-sidebar-foreground/75 hover:text-sidebar-foreground data-[active=true]:text-sidebar-accent-foreground relative h-9 gap-3 transition-colors group-data-[collapsible=icon]:before:hidden data-[active=true]:font-semibold data-[active=true]:before:absolute data-[active=true]:before:top-1.5 data-[active=true]:before:bottom-1.5 data-[active=true]:before:-left-2 data-[active=true]:before:w-1 data-[active=true]:before:rounded-r-full data-[active=true]:before:bg-current"
                    >
                        <Link href={item.url} prefetch>
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                    {item.badge !== null && item.badge !== undefined && (
                        <SidebarMenuBadge
                            aria-label={`${item.badge} services need completion`}
                            // The badge keeps its amber text on hover and while active; the sidebar
                            // default would flip it to the accent foreground and lose all contrast.
                            className="top-1.5 bg-amber-100 font-semibold text-amber-900 peer-hover/menu-button:text-amber-900 peer-data-[active=true]/menu-button:text-amber-900 dark:bg-amber-950 dark:text-amber-200 dark:peer-hover/menu-button:text-amber-200 dark:peer-data-[active=true]/menu-button:text-amber-200"
                        >
                            {item.badge}
                        </SidebarMenuBadge>
                    )}
                </SidebarMenuItem>
            ))}
        </SidebarMenu>
    );
}

const groupLabelClasses = 'text-sidebar-foreground/45 h-7 px-2 text-[0.65rem] font-semibold tracking-[0.14em] uppercase transition-colors';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const page = usePage();
    const currentPath = urlPath(page.url);

    return (
        <>
            {groups.map((group) => {
                if (group.collapsible) {
                    const hasCurrentItem = group.items.some((item) => isCurrent(currentPath, item.url));

                    return (
                        <Collapsible key={group.title} defaultOpen={group.defaultOpen || hasCurrentItem} className="group/collapsible">
                            <SidebarGroup className="px-4 py-1 group-data-[collapsible=icon]:px-2">
                                <CollapsibleTrigger asChild>
                                    <SidebarGroupLabel
                                        className={`${groupLabelClasses} hover:text-sidebar-foreground/80 cursor-pointer justify-between`}
                                    >
                                        {group.title}
                                        <ChevronDown className="size-3.5 transition-transform group-data-[state=closed]/collapsible:-rotate-90" />
                                    </SidebarGroupLabel>
                                </CollapsibleTrigger>
                                {/* Collapsed rail has no visible toggle, so never hide the links there. */}
                                <CollapsibleContent className="group-data-[collapsible=icon]:block">
                                    <NavLinks items={group.items} currentPath={currentPath} />
                                </CollapsibleContent>
                            </SidebarGroup>
                        </Collapsible>
                    );
                }

                return (
                    <SidebarGroup key={group.title} className="px-4 py-1 group-data-[collapsible=icon]:px-2">
                        <SidebarGroupLabel className={groupLabelClasses}>{group.title}</SidebarGroupLabel>
                        <NavLinks items={group.items} currentPath={currentPath} />
                    </SidebarGroup>
                );
            })}
        </>
    );
}
