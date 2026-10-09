import { Form } from '@inertiajs/react';
import { Check, MessageSquare, RotateCcw } from 'lucide-react';
import CommentResolutionController from '@/actions/App/Domain/Comment/Http/Controllers/CommentResolutionController';
import TaskCommentController from '@/actions/App/Domain/Comment/Http/Controllers/TaskCommentController';
import { EmptyState } from '@/components/empty-state';
import { Markdown } from '@/components/markdown/markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TaskComment } from '@/types';

function authorLabel(comment: TaskComment) {
    if (comment.authorType === 'system') {
        return 'System';
    }

    const name = comment.authorName ?? 'Former member';

    return comment.clientName ? `${name} via ${comment.clientName}` : name;
}

function ResolveButton({
    comment,
    args,
}: {
    comment: TaskComment;
    args: { project: string; task: string };
}) {
    const target = { ...args, comment: comment.id };
    const action = comment.resolvedAt
        ? CommentResolutionController.destroy.form(target)
        : CommentResolutionController.store.form(target);

    return (
        <Form {...action} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="ghost"
                    size="sm"
                    disabled={processing}
                >
                    {comment.resolvedAt ? (
                        <>
                            <RotateCcw /> Reopen
                        </>
                    ) : (
                        <>
                            <Check /> Resolve
                        </>
                    )}
                </Button>
            )}
        </Form>
    );
}

export function CommentThread({
    comments,
    taskId,
    projectSlug,
    canUpdate,
}: {
    comments: TaskComment[];
    taskId: string;
    projectSlug: string;
    canUpdate: boolean;
}) {
    const args = { project: projectSlug, task: taskId };

    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-sm font-semibold">Comments</h2>
            {comments.length === 0 && (
                <EmptyState
                    icon={MessageSquare}
                    title="No comments yet"
                    size="sm"
                />
            )}
            {comments.length > 0 && (
                <ol className="flex flex-col border-l">
                    {comments.map((comment) => (
                        <li
                            key={comment.id}
                            className={cn(
                                'relative flex flex-col gap-2 pb-5 pl-5 last:pb-0',
                                comment.resolvedAt && 'opacity-60',
                            )}
                        >
                            <span className="absolute top-1.5 -left-[3.5px] size-1.5 rounded-full bg-border ring-4 ring-background" />
                            <div className="flex items-center justify-between gap-2 text-sm">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {authorLabel(comment)}
                                    </span>
                                    <time
                                        dateTime={comment.createdAt}
                                        title={formatDateTime(
                                            comment.createdAt,
                                        )}
                                        className="font-mono text-xs text-muted-foreground"
                                    >
                                        {formatRelativeTime(comment.createdAt)}
                                    </time>
                                    {comment.resolvedAt && (
                                        <Badge variant="muted">resolved</Badge>
                                    )}
                                </div>
                                {canUpdate && (
                                    <ResolveButton
                                        comment={comment}
                                        args={args}
                                    />
                                )}
                            </div>
                            <Markdown source={comment.bodyMd} />
                        </li>
                    ))}
                </ol>
            )}
            {canUpdate && (
                <Form
                    {...TaskCommentController.store.form(args)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-col gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field data-invalid={!!errors.body_md}>
                                <Textarea
                                    name="body_md"
                                    required
                                    maxLength={10000}
                                    placeholder="Write a comment…"
                                    aria-label="Comment"
                                    aria-invalid={!!errors.body_md}
                                />
                                <FieldDescription>
                                    Markdown. Mention a member with @name.
                                </FieldDescription>
                                <FieldError>{errors.body_md}</FieldError>
                            </Field>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />} Comment
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}
