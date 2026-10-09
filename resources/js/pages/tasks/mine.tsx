import { Head, Link } from '@inertiajs/react';
import { ListTodo } from '@/components/animated-icons';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { show } from '@/routes/projects/tasks';
import { mine } from '@/routes/tasks';
import type { TaskStatus } from '@/types';

type AssignedTask = {
    id: string;
    title: string;
    status: TaskStatus;
    statusLabel: string;
    progressPct: number;
    project: { name: string; slug: string };
};

export default function MyTasks({ tasks }: { tasks: AssignedTask[] }) {
    return (
        <>
            <Head title="My tasks" />
            <Page>
                <PageHeader
                    title="My tasks"
                    description="Open tasks assigned to you in this workspace."
                />
                {tasks.length === 0 ? (
                    <EmptyState
                        icon={ListTodo}
                        title="Nothing assigned to you."
                        description="Tasks assigned to you will show up here."
                    />
                ) : (
                    <Card className="gap-0 py-0">
                        <ul className="divide-y">
                            {tasks.map((task) => (
                                <li key={task.id}>
                                    <Link
                                        href={show({
                                            project: task.project.slug,
                                            task: task.id,
                                        })}
                                        className="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-muted/50"
                                    >
                                        <TaskStatusIcon status={task.status} />
                                        <span className="min-w-0 flex-1 truncate">
                                            {task.title}
                                        </span>
                                        <span className="hidden truncate font-mono text-xs text-muted-foreground sm:inline">
                                            {task.project.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {task.statusLabel}
                                        </Badge>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </Page>
        </>
    );
}

MyTasks.layout = { breadcrumbs: [{ title: 'My tasks', href: mine() }] };
