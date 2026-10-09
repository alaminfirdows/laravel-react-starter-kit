import { Form } from '@inertiajs/react';
import { Check, ShieldQuestion, X } from '@/components/animated-icons';
import ApprovalDecisionController from '@/actions/App/Domain/Task/Http/Controllers/ApprovalDecisionController';
import { Markdown } from '@/components/markdown/markdown';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { Approval } from '@/types';

export function ApprovalBanner({
    approval,
    projectSlug,
    canDecide,
}: {
    approval: Approval;
    projectSlug: string;
    canDecide: boolean;
}) {
    const action = ApprovalDecisionController.store.form({
        project: projectSlug,
        approval: approval.id,
    });

    return (
        <Alert>
            <ShieldQuestion />
            <AlertTitle>
                Approval requested
                {approval.requestedByClient &&
                    ` by ${approval.requestedByClient}`}
            </AlertTitle>
            <AlertDescription className="flex flex-col gap-3">
                <Markdown source={approval.summaryMd} />
                {canDecide && (
                    <div className="flex gap-2">
                        {[true, false].map((approve) => (
                            <Form
                                key={String(approve)}
                                {...action}
                                transform={(data) => ({ ...data, approve })}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        size="sm"
                                        variant={
                                            approve ? 'primary' : 'secondary'
                                        }
                                        disabled={processing}
                                    >
                                        {approve ? <Check /> : <X />}
                                        {approve ? 'Approve' : 'Reject'}
                                    </Button>
                                )}
                            </Form>
                        ))}
                    </div>
                )}
            </AlertDescription>
        </Alert>
    );
}
