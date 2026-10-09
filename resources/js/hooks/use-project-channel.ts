import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';

export function useProjectChannel(projectId: string): void {
    useEcho(
        `projects.${projectId}`,
        ['.task.status-changed', '.run.finished', '.comment.posted'],
        () => router.reload({ only: ['tree', 'task', 'comments'] }),
        [projectId],
    );
}
