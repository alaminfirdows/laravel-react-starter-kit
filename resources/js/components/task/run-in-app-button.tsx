import { Form } from '@inertiajs/react';
import { Bot, ShieldCheck } from 'lucide-react';
import ActionRunController from '@/actions/App/Domain/Task/Http/Controllers/ActionRunController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { TaskAction } from '@/types';

export function RunInAppButton({
    action,
    projectSlug,
    taskId,
}: {
    action: TaskAction;
    projectSlug: string;
    taskId: string;
}) {
    const isRunning = action.status === 'running';
    const isCheck = action.executor === 'app_system';

    return (
        <Form
            {...ActionRunController.store.form({
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
                    disabled={processing || isRunning}
                >
                    {processing || isRunning ? (
                        <Spinner />
                    ) : isCheck ? (
                        <ShieldCheck />
                    ) : (
                        <Bot />
                    )}
                    {isRunning
                        ? 'Running…'
                        : isCheck
                          ? 'Run check'
                          : 'Run with AI'}
                </Button>
            )}
        </Form>
    );
}
