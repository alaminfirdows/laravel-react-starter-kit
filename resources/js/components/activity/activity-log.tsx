import { Link, router } from '@inertiajs/react';
import type { RouteDefinition } from '@/wayfinder';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
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
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2">
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
                <p className="text-sm text-muted-foreground">
                    No activity found.
                </p>
            ) : (
                <ul className="divide-y rounded-md border text-sm">
                    {activity.data.map((item) => (
                        <li
                            key={item.id}
                            className="flex flex-wrap items-center justify-between gap-2 p-3"
                        >
                            <div className="min-w-0 space-y-1">
                                <p className="font-medium">
                                    {describeActivity(item)}
                                </p>
                                <p className="text-muted-foreground">
                                    {actorLabel(item)}
                                    {showProject && item.project && (
                                        <> · {item.project.name}</>
                                    )}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant="outline">{item.channel}</Badge>
                                <time
                                    dateTime={item.createdAt}
                                    className="text-muted-foreground"
                                >
                                    {new Date(item.createdAt).toLocaleString()}
                                </time>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <div className="flex justify-between">
                <Button
                    variant="outline"
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
                    variant="outline"
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
