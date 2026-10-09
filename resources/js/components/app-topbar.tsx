import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { CommandPaletteTrigger } from '@/components/command-palette';
import { NotificationBell } from '@/components/notification-bell';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

/**
 * Sticky top bar inside the content panel: sidebar toggle, breadcrumbs,
 * page-specific extras, search, and notifications.
 */
export function AppTopbar({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children?: ReactNode;
}) {
    return (
        <header className="sticky top-0 z-20 flex h-12 shrink-0 items-center justify-between gap-3 border-b bg-background/85 px-3 backdrop-blur-md supports-[backdrop-filter]:bg-background/70 md:rounded-t-lg md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 text-muted-foreground" />
                {breadcrumbs.length > 0 && (
                    <Separator orientation="vertical" className="mr-1 h-4!" />
                )}
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex shrink-0 items-center gap-2">
                {children}
                <CommandPaletteTrigger className="md:hidden" />
                <NotificationBell />
            </div>
        </header>
    );
}
