import { Deferred, Head } from '@inertiajs/react';
import { BarChart3 } from '@/components/animated-icons';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { ListSkeleton } from '@/components/list-skeleton';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
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
        <Card className="gap-0 py-0">
            <CardHeader className="border-b px-4 py-3">
                <CardTitle className="text-sm font-semibold">{title}</CardTitle>
                <p className="text-xs text-muted-foreground">{description}</p>
            </CardHeader>
            <CardContent className="px-0">
                <Deferred
                    data={data}
                    fallback={<ListSkeleton rows={3} variant="rows" />}
                >
                    {children}
                </Deferred>
            </CardContent>
        </Card>
    );
}

function Empty() {
    return (
        <EmptyState
            icon={BarChart3}
            size="sm"
            title="No data yet."
            className="border-0 bg-transparent"
        />
    );
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
            <Page>
                <PageHeader
                    title="Overview"
                    description="Catalog authoring, pack review and analytics"
                />
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {stats.map((stat) => (
                        <Card key={stat.label} className="gap-1 py-4">
                            <CardHeader className="gap-1 px-4">
                                <p className="text-xs text-muted-foreground">
                                    {stat.label}
                                </p>
                                <CardTitle className="font-mono text-2xl tabular-nums">
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
                            <ul>
                                {completionStats.map((stat) => (
                                    <li
                                        key={stat.key}
                                        className="flex flex-col gap-1.5 border-b px-4 py-3 last:border-b-0"
                                    >
                                        <div className="flex items-center justify-between gap-2 text-sm">
                                            <span className="truncate">
                                                {stat.title}
                                            </span>
                                            <span className="shrink-0 font-mono text-xs text-muted-foreground">
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
                            <ul>
                                {dropOff.map((stat) => (
                                    <li
                                        key={stat.key}
                                        className="flex items-center justify-between gap-2 border-b px-4 py-3 text-sm last:border-b-0"
                                    >
                                        <span className="truncate">
                                            {stat.title}
                                        </span>
                                        <span className="shrink-0 font-mono text-xs text-muted-foreground">
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
            </Page>
        </>
    );
}
