import { Deferred, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import type { TaskCompletionStat, TaskDropOff } from '@/types';

type Props = {
    counts: { tasks: number; actions: number; prompts: number; packs: number };
    windowDays: number;
    completionStats?: TaskCompletionStat[];
    dropOff?: TaskDropOff[];
};

const percent = (rate: number) => `${Math.round(rate * 100)}%`;

function StatList({
    title,
    description,
    data,
    children,
}: {
    title: string;
    description: string;
    data: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <Deferred
                    data={data}
                    fallback={
                        <div className="space-y-2">
                            <Skeleton className="h-8 w-full animate-pulse" />
                            <Skeleton className="h-8 w-full animate-pulse" />
                            <Skeleton className="h-8 w-full animate-pulse" />
                        </div>
                    }
                >
                    {children}
                </Deferred>
            </CardContent>
        </Card>
    );
}

function Empty() {
    return <p className="text-sm text-muted-foreground">No data yet.</p>;
}

export default function AdminIndex({
    counts,
    windowDays,
    completionStats,
    dropOff,
}: Props) {
    const stats = [
        { label: 'Catalog tasks', value: counts.tasks },
        { label: 'Actions', value: counts.actions },
        { label: 'Prompt templates', value: counts.prompts },
        { label: 'Packs', value: counts.packs },
    ];

    return (
        <>
            <Head title="Admin" />
            <div className="space-y-6">
                <Heading variant="small" title="Overview" />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {stats.map((stat) => (
                        <Card key={stat.label}>
                            <CardHeader>
                                <CardDescription>{stat.label}</CardDescription>
                                <CardTitle className="text-2xl">
                                    {stat.value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <StatList
                        title="Task completion"
                        description={`Catalog tasks founders worked on, last ${windowDays} days. All workspaces, totals only.`}
                        data="completionStats"
                    >
                        {completionStats?.length ? (
                            <ul className="divide-y">
                                {completionStats.map((stat) => (
                                    <li
                                        key={stat.key}
                                        className="space-y-1 py-2"
                                    >
                                        <div className="flex items-center justify-between gap-2 text-sm">
                                            <span className="truncate">
                                                {stat.title}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground">
                                                {stat.completed}/{stat.started}{' '}
                                                done · {stat.projects} projects
                                                {stat.avgHoursToComplete !==
                                                    null &&
                                                    ` · ${stat.avgHoursToComplete} h avg`}
                                            </span>
                                        </div>
                                        <Progress
                                            value={stat.completionRate * 100}
                                            aria-label={`${stat.title} completion ${percent(stat.completionRate)}`}
                                        />
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <Empty />
                        )}
                    </StatList>
                    <StatList
                        title="Drop-off"
                        description="Started tasks with no change for 14 days."
                        data="dropOff"
                    >
                        {dropOff?.length ? (
                            <ul className="divide-y">
                                {dropOff.map((stat) => (
                                    <li
                                        key={stat.key}
                                        className="flex items-center justify-between gap-2 py-2 text-sm"
                                    >
                                        <span className="truncate">
                                            {stat.title}
                                        </span>
                                        <span className="shrink-0 text-muted-foreground">
                                            {stat.stalled}/{stat.started}{' '}
                                            stalled ({percent(stat.dropOffRate)}
                                            )
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <Empty />
                        )}
                    </StatList>
                </div>
            </div>
        </>
    );
}
