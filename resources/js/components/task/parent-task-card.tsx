import { Link } from '@inertiajs/react';
import { CornerLeftUp } from 'lucide-react';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { show } from '@/routes/projects/tasks';
import type { TaskSummary } from '@/types';

export function ParentTaskCard({
    parent,
    projectSlug,
}: {
    parent: TaskSummary;
    projectSlug: string;
}) {
    return (
        <Link href={show({ project: projectSlug, task: parent.id })}>
            <Card className="py-4 hover:border-primary">
                <CardHeader className="flex items-center gap-3">
                    <CornerLeftUp className="size-4 text-muted-foreground" />
                    <TaskStatusIcon status={parent.status} />
                    <div className="min-w-0 flex-1">
                        <CardDescription>Part of</CardDescription>
                        <CardTitle className="truncate">
                            {parent.title}
                        </CardTitle>
                    </div>
                    <Progress value={parent.progressPct} className="w-24" />
                    <span className="text-sm tabular-nums">
                        {parent.progressPct}%
                    </span>
                </CardHeader>
            </Card>
        </Link>
    );
}
