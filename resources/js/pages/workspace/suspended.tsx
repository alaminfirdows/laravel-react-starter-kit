import { Head, Link, usePage } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { index as workspacesIndex } from '@/routes/workspaces';

export default function Suspended({ statusLabel }: { statusLabel: string }) {
    const { currentWorkspace } = usePage().props;

    return (
        <>
            <Head title="Workspace unavailable" />

            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                <Ban className="size-10 text-muted-foreground" />
                <div className="space-y-1">
                    <h1 className="text-xl font-semibold">
                        {currentWorkspace?.name} is {statusLabel.toLowerCase()}
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
        </>
    );
}
