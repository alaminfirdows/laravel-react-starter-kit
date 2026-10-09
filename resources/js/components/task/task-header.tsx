import { Form } from '@inertiajs/react';
import { Check, RotateCcw } from 'lucide-react';
import TaskCompletionController from '@/actions/App/Domain/Task/Http/Controllers/TaskCompletionController';
import {
    AssigneePicker,
    type AssigneeOption,
} from '@/components/task/assignee-picker';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { Task } from '@/types';

function CompletionButton({
    task,
    projectSlug,
}: {
    task: Task;
    projectSlug: string;
}) {
    const args = { project: projectSlug, task: task.id };

    if (task.status === 'locked') {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span>
                        <Button disabled>
                            <Check /> Mark as done
                        </Button>
                    </span>
                </TooltipTrigger>
                <TooltipContent>Finish dependencies first</TooltipContent>
            </Tooltip>
        );
    }

    const isDone = task.status === 'done';
    const action = isDone
        ? TaskCompletionController.destroy.form(args)
        : TaskCompletionController.store.form(args);

    return (
        <Form {...action} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant={isDone ? 'outline' : 'default'}
                    disabled={processing}
                >
                    {isDone ? (
                        <>
                            <RotateCcw /> Reopen
                        </>
                    ) : (
                        <>
                            <Check /> Mark as done
                        </>
                    )}
                </Button>
            )}
        </Form>
    );
}

export function TaskHeader({
    task,
    canUpdate,
    projectSlug,
    assignees,
}: {
    task: Task;
    canUpdate: boolean;
    projectSlug: string;
    assignees?: AssigneeOption[];
}) {
    return (
        <PageHeader
            leading={
                <TaskStatusIcon status={task.status} className="mt-1 size-5" />
            }
            title={task.title}
            description={task.summary}
            meta={
                <>
                    <Badge variant="muted">{task.statusLabel}</Badge>
                    <Badge variant="outline" className="font-mono">
                        {task.priority}
                    </Badge>
                    {canUpdate && assignees ? (
                        <AssigneePicker
                            key={task.assignee?.id ?? 'none'}
                            task={task}
                            projectSlug={projectSlug}
                            assignees={assignees}
                        />
                    ) : (
                        task.assignee && (
                            <Badge variant="outline">
                                {task.assignee.name}
                            </Badge>
                        )
                    )}
                </>
            }
            actions={
                task.isLeaf && canUpdate ? (
                    <CompletionButton task={task} projectSlug={projectSlug} />
                ) : undefined
            }
        />
    );
}
