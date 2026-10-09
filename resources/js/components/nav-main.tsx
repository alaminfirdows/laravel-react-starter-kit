import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/**
 * Active item is raised onto a card surface with a tinted icon.
 */
export const navItemClassName =
    'text-sidebar-foreground [&>svg]:text-muted-foreground data-[active=true]:bg-card data-[active=true]:text-foreground data-[active=true]:shadow-xs data-[active=true]:ring-1 data-[active=true]:ring-sidebar-border data-[active=true]:[&>svg]:text-primary';

export function NavMain({
    label,
    items,
}: {
    label?: string;
    items: NavItem[];
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    if (items.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="py-1">
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentOrParentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            className={navItemClassName}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
