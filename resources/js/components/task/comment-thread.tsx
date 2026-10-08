import { Form } from '@inertiajs/react';
import { Check, RotateCcw } from 'lucide-react';
import CommentResolutionController from '@/actions/App/Domain/Comment/Http/Controllers/CommentResolutionController';
import TaskCommentController from '@/actions/App/Domain/Comment/Http/Controllers/TaskCommentController';
import { Markdown } from '@/components/markdown/markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
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
        <section className="space-y-3">
            <h2 className="font-semibold">Comments</h2>
            {comments.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    No comments yet.
                </p>
            )}
            {comments.map((comment) => (
                <div
                    key={comment.id}
                    className={`space-y-2 rounded-lg border p-3 ${comment.resolvedAt ? 'opacity-60' : ''}`}
                >
                    <div className="flex items-center justify-between gap-2 text-sm">
                        <div className="flex items-center gap-2">
                            <span className="font-medium">
                                {authorLabel(comment)}
                            </span>
                            <span className="text-muted-foreground">
                                {new Date(comment.createdAt).toLocaleString()}
                            </span>
                            {comment.resolvedAt && (
                                <Badge variant="secondary">resolved</Badge>
                            )}
                        </div>
                        {canUpdate && (
                            <ResolveButton comment={comment} args={args} />
                        )}
                    </div>
                    <Markdown source={comment.bodyMd} />
                </div>
            ))}
            {canUpdate && (
                <Form
                    {...TaskCommentController.store.form(args)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-2"
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
