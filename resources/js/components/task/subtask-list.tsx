import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import TaskCompletionController from '@/actions/App/Domain/Task/Http/Controllers/TaskCompletionController';
import { TaskStatusIcon } from '@/components/project/task-status-icon';
import { Card } from '@/components/ui/card';
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
        <li className="flex items-center gap-3 border-b px-4 py-2.5 last:border-b-0 hover:bg-muted/50">
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
            <Link href={show(args)} className="min-w-0 flex-1 truncate text-sm">
                {item.title}
            </Link>
            {!item.isLeaf && (
                <span className="font-mono text-xs text-muted-foreground tabular-nums">
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
        <Card className="gap-0 overflow-hidden py-0">
            <ul className="flex flex-col">
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
        </Card>
    );
}
