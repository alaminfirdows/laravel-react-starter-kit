import { Form, Head } from '@inertiajs/react';
import { Copy, Unplug } from 'lucide-react';
import { toast } from 'sonner';
import WorkspaceConnectionController from '@/actions/App/Domain/Workspace/Http/Controllers/WorkspaceConnectionController';
import ConnectClaudeController from '@/actions/App/Http/Controllers/Settings/ConnectClaudeController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@/hooks/use-clipboard';
import { edit as editConnectClaude } from '@/routes/connect-claude';
import type { McpConnection } from '@/types';

const steps = [
    'In Claude, open Settings → Connectors and choose "Add custom connector".',
    'Name it "Founder OS" and paste the connector URL below.',
    'Click Connect, sign in here and pick the workspace Claude may use.',
    'Optional: install the Founder OS plugin to get the task skills.',
];

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function RevokeButton({ disabled }: { disabled: boolean }) {
    return (
        <Button type="submit" size="sm" variant="outline" disabled={disabled}>
            <Unplug /> Revoke
        </Button>
    );
}

export default function ConnectClaude({
    connectorUrl,
    connections,
    team,
}: {
    connectorUrl: string;
    connections: McpConnection[];
    team: {
        workspace: { slug: string; name: string };
        connections: McpConnection[];
    } | null;
}) {
    const [, copy] = useClipboard();

    const copyUrl = async () => {
        if (await copy(connectorUrl)) {
            toast.success('Connector URL copied');
        } else {
            toast.error('Copy failed');
        }
    };

    return (
        <>
            <Head title="Connect Claude" />

            <h1 className="sr-only">Connect Claude</h1>

            <div className="space-y-10">
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Connect Claude"
                        description="Let Claude read your tasks and report progress back"
                    />
                    <ol className="list-decimal space-y-2 pl-5 text-sm">
                        {steps.map((step) => (
                            <li key={step}>{step}</li>
                        ))}
                    </ol>
                    <div className="flex gap-2">
                        <Input
                            readOnly
                            value={connectorUrl}
                            aria-label="Connector URL"
                        />
                        <Button variant="outline" onClick={copyUrl}>
                            <Copy /> Copy
                        </Button>
                    </div>
                </div>

                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Active connections"
                        description="Clients that can act for you. Revoke to sign them out."
                    />
                    {connections.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No connections yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {connections.map((connection) => (
                                <li
                                    key={connection.id}
                                    className="flex items-center justify-between gap-4 p-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {connection.clientName}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {connection.workspaceName ??
                                                'Current workspace'}{' '}
                                            · since{' '}
                                            {formatDate(connection.createdAt)}
                                        </p>
                                    </div>
                                    <Form
                                        {...ConnectClaudeController.destroy.form(
                                            connection.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <RevokeButton
                                                disabled={processing}
                                            />
                                        )}
                                    </Form>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                {team && (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title={`${team.workspace.name} connections`}
                            description="Members' clients bound to this workspace. You can revoke members ranked below you."
                        />
                        {team.connections.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No member connections.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border">
                                {team.connections.map((connection) => (
                                    <li
                                        key={connection.id}
                                        className="flex items-center justify-between gap-4 p-3 text-sm"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {connection.userName}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {connection.clientName} · since{' '}
                                                {formatDate(
                                                    connection.createdAt,
                                                )}
                                            </p>
                                        </div>
                                        {connection.canRevoke && (
                                            <Form
                                                {...WorkspaceConnectionController.destroy.form(
                                                    {
                                                        workspace:
                                                            team.workspace.slug,
                                                        token: connection.id,
                                                    },
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <RevokeButton
                                                        disabled={processing}
                                                    />
                                                )}
                                            </Form>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

ConnectClaude.layout = {
    breadcrumbs: [
        {
            title: 'Connect Claude',
            href: editConnectClaude(),
        },
    ],
};
