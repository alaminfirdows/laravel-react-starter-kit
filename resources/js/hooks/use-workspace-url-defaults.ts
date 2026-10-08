import { usePage } from '@inertiajs/react';
import { setUrlDefaults } from '@/wayfinder';

/**
 * Fill the {workspace} route parameter from the current workspace, like
 * URL::defaults() on the server. Runs during render (not in an effect), so
 * child components can build workspace URLs on their first render.
 */
export function useWorkspaceUrlDefaults(): void {
    const slug = usePage().props.currentWorkspace?.slug;

    setUrlDefaults(slug ? { workspace: slug } : {});
}
