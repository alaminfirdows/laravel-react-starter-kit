import { Deferred, Head, Link, useForm } from '@inertiajs/react';
import { Check, ClipboardCheck, History, X } from '@/components/animated-icons';
import ApprovalDecisionController from '@/actions/App/Domain/Task/Http/Controllers/ApprovalDecisionController';
import { EmptyState } from '@/components/empty-state';
import { ListSkeleton } from '@/components/list-skeleton';
import { Markdown } from '@/components/markdown/markdown';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
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
import { Textarea } from '@/components/ui/textarea';
import { show as showTask } from '@/routes/projects/tasks';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
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
            <Page size="narrow">
                <PageHeader
                    title="Approvals"
                    description="Work that waits for your review before it counts as done."
                />

                <section className="flex flex-col gap-3">
                    {pending.length === 0 && (
                        <EmptyState
                            icon={ClipboardCheck}
                            size="sm"
                            title="Nothing waits for approval."
                            description="Work that needs your review shows up here."
                        />
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

                <section className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">History</h2>
                    <Deferred
                        data="decided"
                        fallback={<ListSkeleton rows={3} variant="rows" />}
                    >
                        <DecidedList
                            approvals={decided ?? []}
                            projectSlug={project.slug}
                        />
                    </Deferred>
                </section>
            </Page>
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
        <Card className="gap-4 py-4">
            <CardHeader className="px-4">
                <CardTitle className="text-sm">
                    <SubjectLink
                        approval={approval}
                        projectSlug={projectSlug}
                    />
                </CardTitle>
                <CardDescription className="flex flex-wrap items-center gap-1">
                    Requested
                    {approval.requestedByClient &&
                        ` by ${approval.requestedByClient}`}
                    {approval.createdAt && (
                        <time
                            dateTime={approval.createdAt}
                            title={formatDateTime(approval.createdAt)}
                            className="font-mono text-xs"
                        >
                            · {formatRelativeTime(approval.createdAt)}
                        </time>
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="px-4">
                <Markdown source={approval.summaryMd} />
            </CardContent>
            {canDecide && (
                <CardFooter className="flex-col items-stretch gap-3 border-t px-4 pt-4">
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
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="primary"
                            size="sm"
                            disabled={form.processing}
                            onClick={() => decide(true)}
                        >
                            <Check data-icon="inline-start" /> Approve
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={form.processing}
                            onClick={() => decide(false)}
                        >
                            <X data-icon="inline-start" /> Reject
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
            <EmptyState
                icon={History}
                size="sm"
                title="No decisions yet."
                description="Approved and rejected work is listed here."
            />
        );
    }

    return (
        <Card className="gap-0 py-0">
            {approvals.map((approval) => (
                <div
                    key={approval.id}
                    className="flex flex-col gap-1 border-b px-4 py-3 text-sm last:border-b-0"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                approval.status === 'approved'
                                    ? 'success'
                                    : 'danger'
                            }
                        >
                            {approval.status}
                        </Badge>
                        <SubjectLink
                            approval={approval}
                            projectSlug={projectSlug}
                        />
                        {approval.decidedAt && (
                            <time
                                dateTime={approval.decidedAt}
                                title={formatDateTime(approval.decidedAt)}
                                className="ml-auto font-mono text-xs text-muted-foreground"
                            >
                                {formatRelativeTime(approval.decidedAt)}
                            </time>
                        )}
                    </div>
                    {approval.decisionNote && (
                        <p className="text-muted-foreground">
                            {approval.decisionNote}
                        </p>
                    )}
                </div>
            ))}
        </Card>
    );
}
