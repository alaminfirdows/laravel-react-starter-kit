import { Head, Link, WhenVisible } from '@inertiajs/react';
import { ArrowRight, History, Info } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { AddPackDialog } from '@/components/project/add-pack-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import { edit } from '@/routes/projects/setup';
import { show as showTask } from '@/routes/projects/tasks';
import type {
    Activity,
    AvailablePack,
    ProjectPageProps,
    TaskSummary,
} from '@/types';

type OverviewProps = ProjectPageProps & {
    nextTask: TaskSummary | null;
    activity?: Activity[];
    availablePacks?: AvailablePack[];
};

export default function ProjectOverview({
    project,
    can,
    tree,
    nextTask,
    activity,
    availablePacks,
}: OverviewProps) {
    const totalTasks = tree.groups.reduce(
        (sum, group) => sum + group.tasks.length,
        0,
    );
    const doneTasks = tree.groups.reduce(
        (sum, group) =>
            sum + group.tasks.filter((task) => task.status === 'done').length,
        0,
    );

    return (
        <>
            <Head title={project.name} />
            <Page>
                {project.setupStep && (
                    <Alert>
                        <Info />
                        <AlertTitle>Setup not finished</AlertTitle>
                        <AlertDescription>
                            <p>
                                Finish setup so tasks and prompts know your
                                company.{' '}
                                <Link
                                    href={edit({
                                        project: project.slug,
                                        step: project.setupStep,
                                    })}
                                    className="font-medium text-foreground underline underline-offset-4"
                                >
                                    Continue setup
                                </Link>
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <PageHeader
                    leading={
                        <WorkspaceAvatar
                            name={project.name}
                            logoUrl={project.logoUrl}
                            className="size-10 rounded-lg"
                        />
                    }
                    title={project.name}
                    description={project.oneLiner}
                    meta={
                        <>
                            <Badge variant="primary">
                                {project.phaseLabel}
                            </Badge>
                            <span className="font-mono text-xs text-muted-foreground tabular">
                                {doneTasks}/{totalTasks} tasks done ·{' '}
                                {tree.progressPct}%
                            </span>
                        </>
                    }
                    actions={
                        <>
                            {can.update && (
                                <AddPackDialog
                                    projectSlug={project.slug}
                                    packs={availablePacks}
                                />
                            )}
                            {nextTask && (
                                <Button asChild>
                                    <Link
                                        href={showTask({
                                            project: project.slug,
                                            task: nextTask.id,
                                        })}
                                    >
                                        <span className="max-w-56 truncate">
                                            Continue: {nextTask.title}
                                        </span>
                                        <ArrowRight data-icon="inline-end" />
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">Workstreams</h2>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {tree.groups.map((group) => {
                            const done = group.tasks.filter(
                                (task) => task.status === 'done',
                            ).length;

                            return (
                                <Card key={group.key} className="gap-3 py-4">
                                    <CardHeader className="flex-row items-center justify-between gap-3 px-4">
                                        <CardTitle className="truncate">
                                            {group.name}
                                        </CardTitle>
                                        <span className="shrink-0 font-mono text-xs text-muted-foreground tabular">
                                            {done}/{group.tasks.length}
                                        </span>
                                    </CardHeader>
                                    <CardContent className="flex items-center gap-3 px-4">
                                        <Progress
                                            value={group.progressPct}
                                            className="h-1.5"
                                            aria-label={`${group.name}: ${group.progressPct}%`}
                                        />
                                        <span className="w-9 shrink-0 text-right font-mono text-xs text-muted-foreground tabular">
                                            {group.progressPct}%
                                        </span>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">Recent activity</h2>
                    <WhenVisible
                        data="activity"
                        fallback={<ActivitySkeleton />}
                    >
                        <ActivityList items={activity ?? []} />
                    </WhenVisible>
                </section>
            </Page>
        </>
    );
}

function ActivitySkeleton() {
    return (
        <Card className="gap-0 py-0">
            {[0, 1, 2, 3].map((i) => (
                <div
                    key={i}
                    className="flex items-center gap-3 border-b px-4 py-3 last:border-b-0"
                >
                    <Skeleton className="size-2 rounded-full" />
                    <Skeleton className="h-4 flex-1" />
                    <Skeleton className="h-4 w-16" />
                </div>
            ))}
        </Card>
    );
}

function ActivityList({ items }: { items: Activity[] }) {
    if (items.length === 0) {
        return (
            <EmptyState
                icon={History}
                size="sm"
                title="No activity yet"
                description="Task updates, decisions, and research show up here."
            />
        );
    }

    return (
        <Card className="gap-0 py-0">
            <ul>
                {items.map((item) => (
                    <li
                        key={item.id}
                        className="flex items-center gap-3 border-b px-4 py-3 text-sm last:border-b-0"
                    >
                        <span
                            aria-hidden
                            className="size-1.5 shrink-0 rounded-full bg-primary/60"
                        />
                        <span className="min-w-0 flex-1 truncate">
                            <span className="first-letter:uppercase">
                                {describe(item)}
                            </span>
                            {item.clientName && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    via {item.clientName}
                                </span>
                            )}
                        </span>
                        <time
                            dateTime={item.createdAt}
                            title={formatDateTime(item.createdAt)}
                            className="shrink-0 font-mono text-xs text-muted-foreground"
                        >
                            {formatRelativeTime(item.createdAt)}
                        </time>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function describe(item: Activity): string {
    const title =
        typeof item.properties.title === 'string'
            ? ` “${item.properties.title}”`
            : '';

    return `${item.event.replace(/[._]/g, ' ')}${title}`;
}
