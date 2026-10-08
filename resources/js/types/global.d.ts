import type { AppNotification, Auth } from '@/types/auth';
import type { UserWorkspace, WorkspacePermissions } from '@/types/workspace';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentWorkspace: UserWorkspace | null;
            workspaces: UserWorkspace[];
            workspacePermissions: WorkspacePermissions | null;
            pendingInvitationsCount: number;
            notifications: {
                unread: number;
                latest: AppNotification[];
            } | null;
            [key: string]: unknown;
        };
    }
}
