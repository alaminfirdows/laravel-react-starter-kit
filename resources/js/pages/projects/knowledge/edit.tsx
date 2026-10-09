import { Form, Head } from '@inertiajs/react';
import KnowledgeController from '@/actions/App/Domain/Knowledge/Http/Controllers/KnowledgeController';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { MarkdownEditor } from '@/components/markdown/markdown-editor';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import type {
    DocStatus,
    KnowledgeDocument,
    Option,
    ProjectPageProps,
} from '@/types';

type KnowledgeEditProps = ProjectPageProps & {
    document: KnowledgeDocument | null;
    defaultType: string | null;
    options: { docTypes: Option[]; statuses: Option<DocStatus>[] };
};

export default function KnowledgeEdit({
    project,
    document,
    defaultType,
    options,
}: KnowledgeEditProps) {
    const form = document
        ? KnowledgeController.update.form({
              project: project.slug,
              knowledgeDocument: document.id,
          })
        : KnowledgeController.store.form({ project: project.slug });

    return (
        <>
            <Head
                title={document ? `Edit ${document.title}` : 'New document'}
            />
            <Page size="narrow">
                <PageHeader
                    title={document ? 'Edit document' : 'New document'}
                    description="Markdown is saved as a new version when the text changes."
                />
                <Form {...form}>
                    {({ processing, errors }) => (
                        <FieldGroup>
                            {!document && (
                                <Field data-invalid={!!errors.doc_type}>
                                    <FieldLabel htmlFor="doc_type">
                                        Type
                                    </FieldLabel>
                                    <Select
                                        name="doc_type"
                                        items={options.docTypes}
                                        defaultValue={defaultType ?? undefined}
                                    >
                                        <SelectTrigger
                                            id="doc_type"
                                            className="w-full"
                                            aria-invalid={!!errors.doc_type}
                                        >
                                            <SelectValue placeholder="Choose a type" />
                                        </SelectTrigger>
                                    </Select>
                                    <FieldError>{errors.doc_type}</FieldError>
                                </Field>
                            )}
                            <Field data-invalid={!!errors.title}>
                                <FieldLabel htmlFor="title">Title</FieldLabel>
                                <Input
                                    id="title"
                                    name="title"
                                    required
                                    maxLength={255}
                                    defaultValue={document?.title}
                                    aria-invalid={!!errors.title}
                                />
                                <FieldError>{errors.title}</FieldError>
                            </Field>
                            <Field data-invalid={!!errors.body_md}>
                                <FieldLabel htmlFor="body_md">
                                    Content
                                </FieldLabel>
                                <MarkdownEditor
                                    id="body_md"
                                    name="body_md"
                                    defaultValue={document?.bodyMd ?? null}
                                    placeholder="Write in Markdown…"
                                />
                                <FieldError>{errors.body_md}</FieldError>
                            </Field>
                            <Field data-invalid={!!errors.status}>
                                <FieldLabel htmlFor="status">Status</FieldLabel>
                                <Select
                                    name="status"
                                    items={options.statuses}
                                    defaultValue={document?.status ?? 'draft'}
                                >
                                    <SelectTrigger
                                        id="status"
                                        className="w-full"
                                        aria-invalid={!!errors.status}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                </Select>
                                <FieldDescription>
                                    Approved ICP, positioning, messaging, brand
                                    and brief feed the project context.
                                    Approving one archives the previous one.
                                </FieldDescription>
                                <FieldError>{errors.status}</FieldError>
                            </Field>
                            {document && (
                                <Field data-invalid={!!errors.change_note}>
                                    <FieldLabel htmlFor="change_note">
                                        Change note
                                    </FieldLabel>
                                    <Input
                                        id="change_note"
                                        name="change_note"
                                        maxLength={255}
                                        placeholder="What changed?"
                                    />
                                    <FieldError>
                                        {errors.change_note}
                                    </FieldError>
                                </Field>
                            )}
                            <Field orientation="horizontal">
                                <Button variant="primary" type="submit" disabled={processing}>
                                    Save document
                                </Button>
                            </Field>
                        </FieldGroup>
                    )}
                </Form>
            </Page>
        </>
    );
}
