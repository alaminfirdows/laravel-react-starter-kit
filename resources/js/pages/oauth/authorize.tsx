import { Head, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import { approve, deny } from '@/routes/passport/authorizations';

type Props = {
    client: {
        id: string;
        name: string;
        verified: boolean;
        redirectHosts: string[];
        createdAt: string | null;
    };
    scopes: { id: string; description: string }[];
    state: string;
    authToken: string;
    csrfToken: string;
    workspaces: { id: string; name: string }[];
    currentWorkspaceId: string | null;
};

/**
 * Passport approve/deny redirect to the client's callback, so these are
 * plain form posts, not Inertia visits.
 */
export default function Authorize({
    client,
    scopes,
    state,
    authToken,
    csrfToken,
    workspaces,
    currentWorkspaceId,
}: Props) {
    const { errors } = usePage().props;
    const defaultWorkspaceId = workspaces.some(
        (workspace) => workspace.id === currentWorkspaceId,
    )
        ? (currentWorkspaceId ?? undefined)
        : workspaces[0]?.id;

    const fields = (
        <>
            <input type="hidden" name="_token" value={csrfToken} />
            <input type="hidden" name="state" value={state} />
            <input type="hidden" name="client_id" value={client.id} />
            <input type="hidden" name="auth_token" value={authToken} />
        </>
    );

    return (
        <>
            <Head title="Authorize" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-1 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                        <strong>{client.name}</strong>
                        {client.verified ? (
                            <Badge variant="secondary">Verified</Badge>
                        ) : (
                            <Badge variant="destructive">Unverified app</Badge>
                        )}
                    </div>
                    <p className="text-muted-foreground">
                        Sends you back to{' '}
                        <strong className="text-foreground">
                            {client.redirectHosts.join(', ')}
                        </strong>
                        {client.createdAt &&
                            ` · Registered ${new Date(client.createdAt).toLocaleDateString()}`}
                    </p>
                    {!client.verified && (
                        <p className="text-muted-foreground">
                            Only continue if you trust this app and this
                            address.
                        </p>
                    )}
                </div>

                <p className="text-sm">
                    <strong>{client.name}</strong> wants to work on your
                    projects in the workspace you choose. It can read tasks and
                    context, start and complete actions, attach evidence and
                    request approvals. It can not delete anything or approve its
                    own requests.
                </p>

                {scopes.length > 0 && (
                    <ul className="flex flex-col gap-1 text-sm text-muted-foreground">
                        {scopes.map((scope) => (
                            <li key={scope.id} className="flex gap-2">
                                <Check className="size-4" />
                                {scope.description}
                            </li>
                        ))}
                    </ul>
                )}

                <form
                    method="post"
                    action={approve.url()}
                    className="flex flex-col gap-6"
                >
                    {fields}
                    <Field data-invalid={!!errors.workspace}>
                        <FieldLabel htmlFor="workspace">Workspace</FieldLabel>
                        <Select
                            name="workspace"
                            required
                            items={workspaces.map((workspace) => ({
                                value: workspace.id,
                                label: workspace.name,
                            }))}
                            defaultValue={defaultWorkspaceId}
                        >
                            <SelectTrigger
                                id="workspace"
                                className="w-full"
                                aria-invalid={!!errors.workspace}
                            >
                                <SelectValue placeholder="Choose a workspace" />
                            </SelectTrigger>
                        </Select>
                        <FieldError>{errors.workspace}</FieldError>
                    </Field>

                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            disabled={workspaces.length === 0}
                        >
                            Authorize
                        </Button>
                        <Button type="submit" variant="outline" form="deny">
                            Cancel
                        </Button>
                    </div>
                </form>
                <form id="deny" method="post" action={deny.url()}>
                    {fields}
                    <input type="hidden" name="_method" value="DELETE" />
                </form>
            </div>
        </>
    );
}

Authorize.layout = {
    title: 'Authorize app',
    description: 'Allow this app to use Founder OS on your behalf.',
};
