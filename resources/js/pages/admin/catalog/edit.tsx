import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Zap } from '@/components/animated-icons';
import CatalogTaskController from '@/actions/App/Domain/Catalog/Http/Controllers/Admin/CatalogTaskController';
import CatalogTaskPublicationController from '@/actions/App/Domain/Catalog/Http/Controllers/Admin/CatalogTaskPublicationController';
import { CatalogActionDialog } from '@/components/admin/catalog-action-dialog';
import { CatalogStatusBadge } from '@/components/admin/catalog-status-badge';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { MarkdownEditor } from '@/components/markdown/markdown-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import { create, edit, index } from '@/routes/admin/catalog';
import type {
    AdminCatalogAction,
    AdminCatalogTask,
    CatalogActionFormOptions,
    CatalogTaskRef,
    Option,
} from '@/types';

type Props = CatalogActionFormOptions & {
    task: AdminCatalogTask | null;
    parent: CatalogTaskRef | null;
    actions: AdminCatalogAction[];
    categoryOptions: Option[];
    priorityOptions: Option[];
};

export default function CatalogEdit({
    task,
    parent,
    actions,
    categoryOptions,
    priorityOptions,
    ...actionOptions
}: Props) {
    const form = task
        ? CatalogTaskController.update.form(task.key)
        : CatalogTaskController.store.form();

    return (
        <>
            <Head title={task ? `Edit ${task.title}` : 'New catalog task'} />
            <Page size="narrow">
                <PageHeader
                    title={task ? task.title : 'New catalog task'}
                    description={
                        parent ? `Subtask of ${parent.title}` : 'Root task'
                    }
                    meta={
                        task && (
                            <>
                                <span className="font-mono text-xs text-muted-foreground">
                                    v{task.version}
                                </span>
                                {task.adminEditedAt && (
                                    <Badge variant="warning">Edited</Badge>
                                )}
                                <CatalogStatusBadge status={task.status} />
                            </>
                        )
                    }
                    actions={
                        <>
                            {task && (
                                <Form
                                    {...CatalogTaskPublicationController.store.form(
                                        task.key,
                                    )}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button variant="secondary" size="sm" disabled={processing}>
                                            {task.status === 'published'
                                                ? 'Publish update'
                                                : 'Publish'}
                                        </Button>
                                    )}
                                </Form>
                            )}
                            <Button size="sm" variant="secondary" asChild>
                                <Link href={index()}>Back</Link>
                            </Button>
                        </>
                    }
                />

                <Form {...form} options={{ preserveScroll: true }}>
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-6">
                            {parent && (
                                <input
                                    type="hidden"
                                    name="parent"
                                    value={parent.key}
                                />
                            )}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm font-semibold">
                                        Basics
                                    </CardTitle>
                                    <CardDescription>
                                        Identity and placement of the task.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Field data-invalid={!!errors.key}>
                                                <FieldLabel htmlFor="key">
                                                    Key
                                                </FieldLabel>
                                                <Input
                                                    id="key"
                                                    name={
                                                        task ? undefined : 'key'
                                                    }
                                                    required
                                                    disabled={!!task}
                                                    defaultValue={task?.key}
                                                    placeholder="research.icp"
                                                    aria-invalid={!!errors.key}
                                                />
                                                <FieldError>
                                                    {errors.key}
                                                </FieldError>
                                            </Field>
                                            {!parent && (
                                                <Field
                                                    data-invalid={
                                                        !!errors.category_id
                                                    }
                                                >
                                                    <FieldLabel htmlFor="category_id">
                                                        Category
                                                    </FieldLabel>
                                                    <Select
                                                        name="category_id"
                                                        items={categoryOptions}
                                                        defaultValue={
                                                            task
                                                                ? String(
                                                                      task.categoryId,
                                                                  )
                                                                : undefined
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="category_id"
                                                            className="w-full"
                                                            aria-invalid={
                                                                !!errors.category_id
                                                            }
                                                        >
                                                            <SelectValue placeholder="Choose a category" />
                                                        </SelectTrigger>
                                                    </Select>
                                                    <FieldError>
                                                        {errors.category_id}
                                                    </FieldError>
                                                </Field>
                                            )}
                                        </div>
                                        <FieldError>{errors.parent}</FieldError>
                                        <Field data-invalid={!!errors.title}>
                                            <FieldLabel htmlFor="title">
                                                Title
                                            </FieldLabel>
                                            <Input
                                                id="title"
                                                name="title"
                                                required
                                                maxLength={255}
                                                defaultValue={task?.title}
                                                aria-invalid={!!errors.title}
                                            />
                                            <FieldError>
                                                {errors.title}
                                            </FieldError>
                                        </Field>
                                        <Field data-invalid={!!errors.summary}>
                                            <FieldLabel htmlFor="summary">
                                                Summary
                                            </FieldLabel>
                                            <Input
                                                id="summary"
                                                name="summary"
                                                maxLength={280}
                                                defaultValue={
                                                    task?.summary ?? ''
                                                }
                                                aria-invalid={!!errors.summary}
                                            />
                                            <FieldError>
                                                {errors.summary}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm font-semibold">
                                        Content
                                    </CardTitle>
                                    <CardDescription>
                                        Shown to founders on the task page.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup>
                                        <Field data-invalid={!!errors.body_md}>
                                            <FieldLabel htmlFor="body_md">
                                                Body
                                            </FieldLabel>
                                            <MarkdownEditor
                                                id="body_md"
                                                name="body_md"
                                                defaultValue={
                                                    task?.bodyMd ?? null
                                                }
                                                placeholder="Explain the task in Markdown…"
                                            />
                                            <FieldError>
                                                {errors.body_md}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm font-semibold">
                                        Settings
                                    </CardTitle>
                                    <CardDescription>
                                        Effort, priority and visibility.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup>
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <Field
                                                data-invalid={!!errors.priority}
                                            >
                                                <FieldLabel htmlFor="priority">
                                                    Priority
                                                </FieldLabel>
                                                <Select
                                                    name="priority"
                                                    items={priorityOptions}
                                                    defaultValue={
                                                        task?.priority ?? 'p2'
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="priority"
                                                        className="w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                </Select>
                                                <FieldError>
                                                    {errors.priority}
                                                </FieldError>
                                            </Field>
                                            <Field
                                                data-invalid={
                                                    !!errors.est_minutes
                                                }
                                            >
                                                <FieldLabel htmlFor="est_minutes">
                                                    Estimate (minutes)
                                                </FieldLabel>
                                                <Input
                                                    id="est_minutes"
                                                    name="est_minutes"
                                                    type="number"
                                                    min={1}
                                                    defaultValue={
                                                        task?.estMinutes ?? ''
                                                    }
                                                />
                                                <FieldError>
                                                    {errors.est_minutes}
                                                </FieldError>
                                            </Field>
                                            <Field
                                                data-invalid={
                                                    !!errors.difficulty
                                                }
                                            >
                                                <FieldLabel htmlFor="difficulty">
                                                    Difficulty (1–5)
                                                </FieldLabel>
                                                <Input
                                                    id="difficulty"
                                                    name="difficulty"
                                                    type="number"
                                                    min={1}
                                                    max={5}
                                                    defaultValue={
                                                        task?.difficulty ?? ''
                                                    }
                                                />
                                                <FieldError>
                                                    {errors.difficulty}
                                                </FieldError>
                                            </Field>
                                        </div>
                                        <Field orientation="horizontal">
                                            <Checkbox
                                                id="is_optional"
                                                name="is_optional"
                                                value="1"
                                                defaultChecked={
                                                    task?.isOptional
                                                }
                                            />
                                            <FieldLabel htmlFor="is_optional">
                                                Optional task
                                            </FieldLabel>
                                        </Field>
                                        <FieldDescription>
                                            New tasks start as drafts. Projects
                                            only get published tasks.
                                        </FieldDescription>
                                    </FieldGroup>
                                </CardContent>
                            </Card>
                            <div className="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-2 border-t bg-background/90 px-4 py-3 backdrop-blur md:-mx-8 md:px-8">
                                <Button variant="primary" type="submit" disabled={processing}>
                                    {task ? 'Save task' : 'Create draft'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>

                {task && (
                    <Card className="gap-0 py-0">
                        <CardHeader className="flex flex-row items-center justify-between border-b px-4 py-3">
                            <CardTitle className="text-sm font-semibold">
                                Actions
                            </CardTitle>
                            <div className="flex gap-2">
                                <Button size="sm" variant="secondary" asChild>
                                    <Link
                                        href={create({
                                            query: { parent: task.key },
                                        })}
                                    >
                                        <Plus data-icon="inline-start" />{' '}
                                        Subtask
                                    </Link>
                                </Button>
                                <CatalogActionDialog
                                    taskKey={task.key}
                                    {...actionOptions}
                                    trigger={
                                        <Button variant="secondary" size="sm">
                                            <Plus data-icon="inline-start" />{' '}
                                            Action
                                        </Button>
                                    }
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {actions.length > 0 ? (
                                <ul>
                                    {actions.map((action) => (
                                        <li
                                            key={action.key}
                                            className="flex items-center gap-2 border-b px-4 py-2 last:border-b-0"
                                        >
                                            <span className="min-w-0 flex-1 truncate text-sm">
                                                {action.title}
                                            </span>
                                            <Badge variant="outline">
                                                {action.type}
                                            </Badge>
                                            <Badge variant="secondary">
                                                {action.executor}
                                            </Badge>
                                            <CatalogActionDialog
                                                taskKey={task.key}
                                                action={action}
                                                {...actionOptions}
                                                trigger={
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Edit ${action.title}`}
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                }
                                            />
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState
                                    icon={Zap}
                                    size="sm"
                                    title="No actions yet."
                                    className="border-0 bg-transparent"
                                />
                            )}
                        </CardContent>
                    </Card>
                )}
                {parent && (
                    <Button variant="link" className="px-0" asChild>
                        <Link href={edit(parent.key)}>
                            Back to {parent.title}
                        </Link>
                    </Button>
                )}
            </Page>
        </>
    );
}
