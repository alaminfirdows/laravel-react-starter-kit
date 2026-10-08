import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import TaskCompletionController from '@/actions/App/Domain/Task/Http/Controllers/TaskCompletionController';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import { Checkbox } from '@/components/ui/checkbox';
import { show } from '@/routes/projects/tasks';
import type { TaskSummary } from '@/types';

function Row({
    item,
    projectSlug,
    canUpdate,
}: {
    item: TaskSummary;
    projectSlug: string;
    canUpdate: boolean;
}) {
    const [checked, setChecked] = useState(item.status === 'done');
    const args = { project: projectSlug, task: item.id };

    const toggle = (next: boolean) => {
        setChecked(next);
        router.visit(
            next
                ? TaskCompletionController.store(args)
                : TaskCompletionController.destroy(args),
            {
                preserveScroll: true,
                onError: () => setChecked(!next),
            },
        );
    };

    return (
        <li className="flex items-center gap-3 rounded-md border p-3">
            {item.isLeaf ? (
                <Checkbox
                    checked={checked}
                    disabled={!canUpdate || item.status === 'locked'}
                    onCheckedChange={(value) => toggle(value === true)}
                    aria-label={`Mark ${item.title} as done`}
                />
            ) : (
                <TaskStatusIcon status={item.status} />
            )}
            <Link
                href={show(args)}
                className="min-w-0 flex-1 truncate hover:underline"
            >
                {item.title}
            </Link>
            {!item.isLeaf && (
                <span className="text-xs text-muted-foreground tabular-nums">
                    {item.progressPct}%
                </span>
            )}
        </li>
    );
}

export function SubtaskList({
    items,
    projectSlug,
    canUpdate,
}: {
    items: TaskSummary[];
    projectSlug: string;
    canUpdate: boolean;
}) {
    return (
        <ul className="space-y-2">
            {items.map((item) => (
                // Status in the key resets local optimistic state when the server disagrees.
                <Row
                    key={`${item.id}-${item.status}`}
                    item={item}
                    projectSlug={projectSlug}
                    canUpdate={canUpdate}
                />
            ))}
        </ul>
    );
}
