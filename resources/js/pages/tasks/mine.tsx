import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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
            <div className="space-y-6 p-4 md:p-8">
                <Heading
                    title="My tasks"
                    description="Open tasks assigned to you in this workspace."
                />
                {tasks.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-12 text-center text-muted-foreground">
                        Nothing assigned to you.
                    </div>
                ) : (
                    <Card className="py-2">
                        <CardContent className="divide-y px-2">
                            {tasks.map((task) => (
                                <Link
                                    key={task.id}
                                    href={show({
                                        project: task.project.slug,
                                        task: task.id,
                                    })}
                                    className="flex items-center gap-3 rounded-md px-2 py-3 hover:bg-muted"
                                >
                                    <TaskStatusIcon status={task.status} />
                                    <span className="min-w-0 flex-1 truncate">
                                        {task.title}
                                    </span>
                                    <Badge variant="outline">
                                        {task.project.name}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {task.statusLabel}
                                    </Badge>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

MyTasks.layout = { breadcrumbs: [{ title: 'My tasks', href: mine() }] };
