import { Link, router } from '@inertiajs/react';
import { Activity as ActivityIcon } from '@/components/animated-icons';
import type { RouteDefinition } from '@/wayfinder';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import type {
    Activity,
    ActivityFilterValues,
    ActivityLogProps,
    Option,
} from '@/types';

const ALL = 'all';

export function describeActivity(item: Activity): string {
    const title =
        typeof item.properties.title === 'string'
            ? ` “${item.properties.title}”`
            : '';

    return `${item.event.replace(/[._]/g, ' ')}${title}`;
}

function actorLabel(item: Activity): string {
    if (item.actorType === 'system') {
        return 'System';
    }

    const name = item.actorName ?? 'Former member';

    return item.clientName ? `${name} via ${item.clientName}` : name;
}

function FilterSelect({
    label,
    value,
    items,
    onChange,
}: {
    label: string;
    value: string | null;
    items: Option[];
    onChange: (value: string | null) => void;
}) {
    return (
        <Select
            value={value ?? ALL}
            onValueChange={(next) => onChange(next === ALL ? null : next)}
            items={[{ value: ALL, label: `All ${label}` }, ...items]}
        >
            <SelectTrigger size="sm" aria-label={label}>
                <SelectValue />
            </SelectTrigger>
        </Select>
    );
}

export function ActivityLog({
    activity,
    filters,
    options,
    href,
    showProject = false,
}: ActivityLogProps & { href: RouteDefinition<'get'>; showProject?: boolean }) {
    const apply = (key: keyof ActivityFilterValues, value: string | null) => {
        const next = { ...filters, [key]: value };

        router.get(
            href.url,
            Object.fromEntries(
                Object.entries(next).filter(([, v]) => v !== null),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-2">
                <FilterSelect
                    label="actors"
                    value={filters.actor}
                    items={options.actors}
                    onChange={(value) => apply('actor', value)}
                />
                <FilterSelect
                    label="channels"
                    value={filters.channel}
                    items={options.channels}
                    onChange={(value) => apply('channel', value)}
                />
                <FilterSelect
                    label="events"
                    value={filters.event}
                    items={options.events}
                    onChange={(value) => apply('event', value)}
                />
            </div>
            {activity.data.length === 0 ? (
                <EmptyState
                    icon={ActivityIcon}
                    size="sm"
                    title="No activity found"
                    description="Changes will show up here as they happen."
                />
            ) : (
                <Card className="gap-0 py-0">
                    <ul className="flex flex-col text-sm">
                        {activity.data.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3 last:border-b-0"
                            >
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <p className="font-medium capitalize">
                                        {describeActivity(item)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {actorLabel(item)}
                                        {showProject && item.project && (
                                            <> · {item.project.name}</>
                                        )}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="muted"
                                        className="font-mono"
                                    >
                                        {item.channel}
                                    </Badge>
                                    <time
                                        dateTime={item.createdAt}
                                        title={formatDateTime(item.createdAt)}
                                        className="font-mono text-xs text-muted-foreground"
                                    >
                                        {formatRelativeTime(item.createdAt)}
                                    </time>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
            <div className="flex items-center justify-between">
                <Button
                    variant="secondary"
                    size="sm"
                    asChild={!!activity.links.prev}
                    disabled={!activity.links.prev}
                >
                    {activity.links.prev ? (
                        <Link href={activity.links.prev} preserveScroll>
                            Newer
                        </Link>
                    ) : (
                        'Newer'
                    )}
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    asChild={!!activity.links.next}
                    disabled={!activity.links.next}
                >
                    {activity.links.next ? (
                        <Link href={activity.links.next} preserveScroll>
                            Older
                        </Link>
                    ) : (
                        'Older'
                    )}
                </Button>
            </div>
        </div>
    );
}
