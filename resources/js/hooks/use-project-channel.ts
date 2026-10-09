import { router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef } from 'react';

const RELOAD_DEBOUNCE_MS = 300;

export function useProjectChannel(projectId: string): void {
    const reloadTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(
        () => () => {
            if (reloadTimeout.current !== null) {
                clearTimeout(reloadTimeout.current);
            }
        },
        [],
    );

    useEcho(
        `projects.${projectId}`,
        ['.task.status-changed', '.run.finished', '.comment.posted'],
        () => {
            if (reloadTimeout.current !== null) {
                clearTimeout(reloadTimeout.current);
            }

            reloadTimeout.current = setTimeout(() => {
                reloadTimeout.current = null;
                router.reload({ only: ['tree', 'task', 'comments'] });
            }, RELOAD_DEBOUNCE_MS);
        },
        [projectId],
    );
}
