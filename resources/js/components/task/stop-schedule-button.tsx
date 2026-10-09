import { Form } from '@inertiajs/react';
import { CalendarX } from 'lucide-react';
import ActionScheduleController from '@/actions/App/Domain/Task/Http/Controllers/ActionScheduleController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { TaskAction } from '@/types';

export function StopScheduleButton({
    action,
    projectSlug,
    taskId,
}: {
    action: TaskAction;
    projectSlug: string;
    taskId: string;
}) {
    return (
        <Form
            {...ActionScheduleController.destroy.form({
                project: projectSlug,
                task: taskId,
                action: action.id,
            })}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={processing}
                >
                    {processing ? <Spinner /> : <CalendarX />}
                    Stop schedule
                </Button>
            )}
        </Form>
    );
}
