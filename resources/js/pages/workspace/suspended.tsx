import { Head, Link, usePage } from '@inertiajs/react';
import { Ban } from '@/components/animated-icons';
import { Button } from '@/components/ui/button';
import { index as workspacesIndex } from '@/routes/workspaces';

export default function Suspended({ statusLabel }: { statusLabel: string }) {
    const { currentWorkspace } = usePage().props;

    return (
        <>
            <Head title="Workspace unavailable" />

            <div className="flex flex-1 items-center justify-center p-6">
                <div className="flex w-full max-w-sm flex-col items-center gap-4 rounded-xl border bg-card p-6 text-center shadow-xs">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                        <Ban className="size-5" />
                    </span>
                    <div className="flex flex-col gap-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {currentWorkspace?.name} is{' '}
                            {statusLabel.toLowerCase()}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            This workspace is not available. Contact support or
                            switch to another workspace.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={workspacesIndex()}>All workspaces</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
