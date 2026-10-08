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
                    size="icon"
                    className="relative"
                    aria-label="Notifications"
                >
                    <Bell />
                    {notifications.unread > 0 && (
                        <Badge className="absolute -top-1 -right-1 h-4 min-w-4 px-1 text-[10px]">
                            {notifications.unread}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    Notifications
                    {notifications.unread > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
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
                <DropdownMenuSeparator />
                {notifications.latest.length === 0 && (
                    <p className="p-2 text-sm text-muted-foreground">
                        You are up to date.
                    </p>
                )}
                {notifications.latest.map((notification) => (
                    <DropdownMenuItem
                        key={notification.id}
                        className="flex-col items-start gap-0.5"
                        onSelect={() =>
                            router.visit(
                                NotificationController.read(notification.id),
                            )
                        }
                    >
                        <span className="font-medium">
                            {notification.title}
                        </span>
                        <span className="line-clamp-2 text-xs text-muted-foreground">
                            {notification.body}
                        </span>
                        {notification.project && (
                            <span className="text-xs text-muted-foreground">
                                {notification.project}
                            </span>
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
