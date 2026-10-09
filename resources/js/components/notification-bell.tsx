import { router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function NotificationBell() {
    const { notifications } = usePage().props;

    if (!notifications) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    className="relative"
                    aria-label={
                        notifications.unread > 0
                            ? `Notifications, ${notifications.unread} unread`
                            : 'Notifications'
                    }
                >
                    <Bell />
                    {notifications.unread > 0 && (
                        <Badge className="absolute -top-0.5 -right-0.5 h-4 min-w-4 rounded-full px-1 font-mono text-[10px] ring-2 ring-background">
                            {notifications.unread > 9
                                ? '9+'
                                : notifications.unread}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <DropdownMenuLabel className="flex h-11 items-center justify-between px-3 text-sm font-semibold">
                    Notifications
                    {notifications.unread > 0 && (
                        <Button
                            variant="ghost"
                            size="xs"
                            onClick={() =>
                                router.visit(NotificationController.readAll(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <CheckCheck /> Mark all read
                        </Button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="my-0" />
                {notifications.latest.length === 0 && (
                    <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
                        <Bell className="size-5 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            You are up to date.
                        </p>
                    </div>
                )}
                <div className="max-h-96 overflow-y-auto p-1">
                    {notifications.latest.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            className="relative flex-col items-start gap-0.5 py-2 pr-2 pl-6"
                            onSelect={() =>
                                router.visit(
                                    NotificationController.read(
                                        notification.id,
                                    ),
                                )
                            }
                        >
                            <span
                                aria-hidden
                                className="absolute top-3.5 left-2.5 size-1.5 rounded-full bg-primary"
                            />
                            <span className="font-medium">
                                {notification.title}
                            </span>
                            <span className="line-clamp-2 text-xs text-muted-foreground">
                                {notification.body}
                            </span>
                            {notification.project && (
                                <span className="text-xs font-medium text-muted-foreground/80">
                                    {notification.project}
                                </span>
                            )}
                        </DropdownMenuItem>
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
