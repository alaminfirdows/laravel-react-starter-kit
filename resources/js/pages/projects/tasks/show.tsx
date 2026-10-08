import { Head, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import { Markdown } from '@/components/markdown/markdown';
import { ActionCard } from '@/components/task/action-card';
import { ParentTaskCard } from '@/components/task/parent-task-card';
import { SubtaskList } from '@/components/task/subtask-list';
import { TaskHeader } from '@/components/task/task-header';
import type { ProjectPageProps, Task } from '@/types';

export default function TaskShow({
    project,
    can,
    task,
}: ProjectPageProps & { task: Task }) {
    const { start, stop } = usePoll(
        10_000,
        { only: ['task'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (task.hasActiveRun) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [task.hasActiveRun, start, stop]);

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
                />
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
            </div>
        </>
    );
}
