import { Deferred, Head, Link, useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import ApprovalDecisionController from '@/actions/App/Domain/Task/Http/Controllers/ApprovalDecisionController';
import Heading from '@/components/heading';
import { Markdown } from '@/components/markdown/markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { show as showTask } from '@/routes/projects/tasks';
import type { Approval, ProjectPageProps } from '@/types';

type Props = ProjectPageProps & {
    pending: Approval[];
    decided?: Approval[];
};

export default function ApprovalsIndex({
    project,
    can,
    pending,
    decided,
}: Props) {
    return (
        <>
            <Head title={`Approvals · ${project.name}`} />
            <div className="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-8">
                <Heading
                    title="Approvals"
                    description="Work that waits for your review before it counts as done."
                />

                <section className="space-y-4">
                    {pending.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nothing waits for approval.
                        </p>
                    )}
                    {pending.map((approval) => (
                        <PendingApproval
                            key={approval.id}
                            approval={approval}
                            projectSlug={project.slug}
                            canDecide={can.update}
                        />
                    ))}
                </section>

                <section className="space-y-4">
                    <h2 className="text-sm font-medium">History</h2>
                    <Deferred
                        data="decided"
                        fallback={
                            <div className="space-y-2">
                                <Skeleton className="h-16 w-full animate-pulse" />
                                <Skeleton className="h-16 w-full animate-pulse" />
                            </div>
                        }
                    >
                        <DecidedList
                            approvals={decided ?? []}
                            projectSlug={project.slug}
                        />
                    </Deferred>
                </section>
            </div>
        </>
    );
}

function SubjectLink({
    approval,
    projectSlug,
}: {
    approval: Approval;
    projectSlug: string;
}) {
    if (!approval.subject) {
        return null;
    }

    return (
        <Link
            href={showTask({
                project: projectSlug,
                task: approval.subject.taskId,
            })}
            className="underline-offset-4 hover:underline"
        >
            {approval.subject.taskTitle === approval.subject.title
                ? approval.subject.title
                : `${approval.subject.taskTitle} · ${approval.subject.title}`}
        </Link>
    );
}

function PendingApproval({
    approval,
    projectSlug,
    canDecide,
}: {
    approval: Approval;
    projectSlug: string;
    canDecide: boolean;
}) {
    const form = useForm({ note: '' });

    const decide = (approve: boolean) => {
        form.transform((data) => ({ ...data, approve }));
        form.submit(
            ApprovalDecisionController.store({
                project: projectSlug,
                approval: approval.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    <SubjectLink
                        approval={approval}
                        projectSlug={projectSlug}
                    />
                </CardTitle>
                <CardDescription>
                    Requested
                    {approval.requestedByClient &&
                        ` by ${approval.requestedByClient}`}
                    {approval.createdAt &&
                        ` · ${new Date(approval.createdAt).toLocaleString()}`}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Markdown source={approval.summaryMd} />
            </CardContent>
            {canDecide && (
                <CardFooter className="flex-col items-stretch gap-3">
                    <Field data-invalid={!!form.errors.note}>
                        <FieldLabel htmlFor={`note-${approval.id}`}>
                            Note
                        </FieldLabel>
                        <Textarea
                            id={`note-${approval.id}`}
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                            placeholder="Optional. Why you approve or reject."
                            aria-invalid={!!form.errors.note}
                        />
                        <FieldError>{form.errors.note}</FieldError>
                    </Field>
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            disabled={form.processing}
                            onClick={() => decide(true)}
                        >
                            <Check /> Approve
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => decide(false)}
                        >
                            <X /> Reject
                        </Button>
                    </div>
                </CardFooter>
            )}
        </Card>
    );
}

function DecidedList({
    approvals,
    projectSlug,
}: {
    approvals: Approval[];
    projectSlug: string;
}) {
    if (approvals.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No decisions yet.</p>
        );
    }

    return (
        <div className="space-y-2">
            {approvals.map((approval) => (
                <Card key={approval.id} className="py-4">
                    <CardContent className="space-y-1 text-sm">
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    approval.status === 'approved'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {approval.status}
                            </Badge>
                            <SubjectLink
                                approval={approval}
                                projectSlug={projectSlug}
                            />
                            {approval.decidedAt && (
                                <span className="ml-auto text-muted-foreground">
                                    {new Date(
                                        approval.decidedAt,
                                    ).toLocaleString()}
                                </span>
                            )}
                        </div>
                        {approval.decisionNote && (
                            <p className="text-muted-foreground">
                                {approval.decisionNote}
                            </p>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
