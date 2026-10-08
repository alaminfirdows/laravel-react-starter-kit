import { Head } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { approve, deny } from '@/routes/passport/authorizations';

type Props = {
    client: { id: string; name: string };
    scopes: { id: string; description: string }[];
    state: string;
    authToken: string;
    csrfToken: string;
    workspace: { id: string; name: string } | null;
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
    workspace,
}: Props) {
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

            <div className="space-y-6">
                <p className="text-sm">
                    <strong>{client.name}</strong> wants to work on your
                    projects
                    {workspace && (
                        <>
                            {' '}
                            in <strong>{workspace.name}</strong>
                        </>
                    )}
                    . It can read tasks and context, start and complete actions,
                    attach evidence and request approvals. It can not delete
                    anything or approve its own requests.
                </p>

                {scopes.length > 0 && (
                    <ul className="space-y-1 text-sm text-muted-foreground">
                        {scopes.map((scope) => (
                            <li key={scope.id} className="flex gap-2">
                                <Check className="size-4" />
                                {scope.description}
                            </li>
                        ))}
                    </ul>
                )}

                <div className="flex gap-2">
                    <form method="post" action={approve.url()}>
                        {fields}
                        <Button type="submit">Authorize</Button>
                    </form>
                    <form method="post" action={deny.url()}>
                        {fields}
                        <input type="hidden" name="_method" value="DELETE" />
                        <Button type="submit" variant="outline">
                            Cancel
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}

Authorize.layout = {
    title: 'Connect Claude',
    description: 'Allow this app to use Founder OS on your behalf.',
};
