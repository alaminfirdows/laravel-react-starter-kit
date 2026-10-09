import { Head, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { Markdown } from '@/components/markdown/markdown';
import { ActionCard } from '@/components/task/action-card';
import { CatalogUpdateDialog } from '@/components/task/catalog-update-dialog';
import { CommentThread } from '@/components/task/comment-thread';
import { ParentTaskCard } from '@/components/task/parent-task-card';
import { SubtaskList } from '@/components/task/subtask-list';
import { TaskHeader } from '@/components/task/task-header';
import type { AssigneeOption } from '@/components/task/assignee-picker';
import type {
    CatalogFieldDiff,
    ProjectPageProps,
    Task,
    TaskComment,
} from '@/types';

export default function TaskShow({
    project,
    can,
    task,
    assignees,
    comments,
    catalogDiff,
}: ProjectPageProps & {
    task: Task;
    assignees: AssigneeOption[];
    comments: TaskComment[];
    catalogDiff?: CatalogFieldDiff[];
}) {
    const { start, stop } = usePoll(
        10_000,
        { only: ['task'] },
        { autoStart: false },
    );
    const shouldPoll =
        task.hasActiveRun && !import.meta.env.VITE_PUSHER_APP_KEY;

    useEffect(() => {
        if (shouldPoll) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [shouldPoll, start, stop]);

    return (
        <>
            <Head title={task.title} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-8">
                {task.parent && (
                    <ParentTaskCard
                        parent={task.parent}
                        projectSlug={project.slug}
                    />
                )}
                <TaskHeader
                    task={task}
                    canUpdate={can.update}
                    projectSlug={project.slug}
                    assignees={assignees}
                />
                {task.hasCatalogUpdate && can.update && (
                    <CatalogUpdateDialog
                        taskId={task.id}
                        projectSlug={project.slug}
                        diff={catalogDiff}
                    />
                )}
                <Markdown source={task.bodyMd} />
                {task.children.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">Subtasks</h2>
                        <SubtaskList
                            items={task.children}
                            projectSlug={project.slug}
                            canUpdate={can.update}
                        />
                    </section>
                )}
                {task.actions.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">How to get it done</h2>
                        {task.actions.map((action) => (
                            <ActionCard
                                key={action.id}
                                taskId={task.id}
                                action={action}
                                projectSlug={project.slug}
                                canUpdate={can.update}
                            />
                        ))}
                    </section>
                )}
                <CommentThread
                    comments={comments}
                    taskId={task.id}
                    projectSlug={project.slug}
                    canUpdate={can.update}
                />
            </div>
        </>
    );
}
