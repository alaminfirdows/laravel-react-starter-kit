import { useForm } from '@inertiajs/react';
import TaskAssigneeController from '@/actions/App/Domain/Task/Http/Controllers/TaskAssigneeController';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Task } from '@/types';

const UNASSIGNED = 'none';

export type AssigneeOption = { value: string; label: string };

export function AssigneePicker({
    task,
    projectSlug,
    assignees,
}: {
    task: Task;
    projectSlug: string;
    assignees: AssigneeOption[];
}) {
    const form = useForm({ assignee_id: task.assignee?.id ?? UNASSIGNED });

    const change = (value: string) => {
        form.setData('assignee_id', value);
        form.transform(() => ({
            assignee_id: value === UNASSIGNED ? null : value,
        }));
        form.submit(
            TaskAssigneeController.update({
                project: projectSlug,
                task: task.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <Select
            value={form.data.assignee_id}
            onValueChange={change}
            disabled={form.processing}
            items={[{ value: UNASSIGNED, label: 'Unassigned' }, ...assignees]}
        >
            <SelectTrigger
                size="sm"
                aria-label="Assignee"
                aria-invalid={!!form.errors.assignee_id}
            >
                <SelectValue />
            </SelectTrigger>
        </Select>
    );
}
