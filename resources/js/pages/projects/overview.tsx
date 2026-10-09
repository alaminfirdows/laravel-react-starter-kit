import { Head, Link, WhenVisible } from '@inertiajs/react';
import { ArrowRight, Info } from 'lucide-react';
import { AddPackDialog } from '@/components/project/add-pack-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
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
    return (
        <>
            <Head title={project.name} />
            <div className="mx-auto w-full max-w-5xl space-y-8 p-4 md:p-8">
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
                                    className="underline"
                                >
                                    Continue setup
                                </Link>
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {project.name}
                            </h1>
                            <Badge variant="secondary">
                                {project.phaseLabel}
                            </Badge>
                        </div>
                        {project.oneLiner && (
                            <p className="text-muted-foreground">
                                {project.oneLiner}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
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
                                    Continue: {nextTask.title} <ArrowRight />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {tree.groups.map((group) => {
                        const done = group.tasks.filter(
                            (task) => task.status === 'done',
                        ).length;

                        return (
                            <Card key={group.key}>
                                <CardHeader>
                                    <CardTitle>{group.name}</CardTitle>
                                    <CardDescription>
                                        {done} of {group.tasks.length} tasks
                                        done
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Progress
                                        value={group.progressPct}
                                        aria-label={`${group.name}: ${group.progressPct}%`}
                                    />
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent activity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <WhenVisible
                            data="activity"
                            fallback={<ActivitySkeleton />}
                        >
                            <ActivityList items={activity ?? []} />
                        </WhenVisible>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ActivitySkeleton() {
    return (
        <div className="space-y-3">
            {[0, 1, 2].map((i) => (
                <Skeleton key={i} className="h-5 w-full animate-pulse" />
            ))}
        </div>
    );
}

function ActivityList({ items }: { items: Activity[] }) {
    if (items.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No activity yet.</p>
        );
    }

    return (
        <ul className="space-y-2 text-sm">
            {items.map((item) => (
                <li key={item.id} className="flex justify-between gap-4">
                    <span>
                        {describe(item)}
                        {item.clientName && (
                            <span className="text-muted-foreground">
                                {' '}
                                via {item.clientName}
                            </span>
                        )}
                    </span>
                    <time
                        dateTime={item.createdAt}
                        className="shrink-0 text-muted-foreground"
                    >
                        {new Date(item.createdAt).toLocaleString()}
                    </time>
                </li>
            ))}
        </ul>
    );
}

function describe(item: Activity): string {
    const title =
        typeof item.properties.title === 'string'
            ? ` “${item.properties.title}”`
            : '';

    return `${item.event.replace(/[._]/g, ' ')}${title}`;
}
