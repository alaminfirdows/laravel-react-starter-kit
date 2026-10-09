import { Form, Head, Link } from '@inertiajs/react';
import PromptTemplateController from '@/actions/App/Domain/Catalog/Http/Controllers/Admin/PromptTemplateController';
import Heading from '@/components/heading';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/prompts';
import type { AdminPromptTemplate, Option } from '@/types';

type Props = {
    prompt: AdminPromptTemplate | null;
    targetOptions: Option[];
};

export default function PromptEdit({ prompt, targetOptions }: Props) {
    const form = prompt
        ? PromptTemplateController.update.form(prompt.key)
        : PromptTemplateController.store.form();

    return (
        <>
            <Head title={prompt ? `Edit ${prompt.title}` : 'New prompt'} />
            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={prompt ? prompt.title : 'New prompt'}
                        description={
                            prompt ? `Version ${prompt.version}` : undefined
                        }
                    />
                    <Button size="sm" variant="outline" asChild>
                        <Link href={index()}>Back</Link>
                    </Button>
                </div>
                <Form {...form} options={{ preserveScroll: true }}>
                    {({ processing, errors }) => (
                        <FieldGroup>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field data-invalid={!!errors.key}>
                                    <FieldLabel htmlFor="key">Key</FieldLabel>
                                    <Input
                                        id="key"
                                        name={prompt ? undefined : 'key'}
                                        required
                                        disabled={!!prompt}
                                        defaultValue={prompt?.key}
                                        aria-invalid={!!errors.key}
                                    />
                                    <FieldError>{errors.key}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.target}>
                                    <FieldLabel htmlFor="target">
                                        Target
                                    </FieldLabel>
                                    <Select
                                        name="target"
                                        items={targetOptions}
                                        defaultValue={prompt?.target ?? 'chat'}
                                    >
                                        <SelectTrigger
                                            id="target"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                    </Select>
                                    <FieldError>{errors.target}</FieldError>
                                </Field>
                            </div>
                            <Field data-invalid={!!errors.title}>
                                <FieldLabel htmlFor="title">Title</FieldLabel>
                                <Input
                                    id="title"
                                    name="title"
                                    required
                                    defaultValue={prompt?.title}
                                    aria-invalid={!!errors.title}
                                />
                                <FieldError>{errors.title}</FieldError>
                            </Field>
                            <Field data-invalid={!!errors.launcher_md}>
                                <FieldLabel htmlFor="launcher_md">
                                    Launcher prompt
                                </FieldLabel>
                                <Textarea
                                    id="launcher_md"
                                    name="launcher_md"
                                    rows={3}
                                    defaultValue={prompt?.launcherMd ?? ''}
                                />
                                <FieldDescription>
                                    IDs and skill names only, never company
                                    data.
                                </FieldDescription>
                                <FieldError>{errors.launcher_md}</FieldError>
                            </Field>
                            <Field data-invalid={!!errors.full_md}>
                                <FieldLabel htmlFor="full_md">
                                    Full prompt
                                </FieldLabel>
                                <Textarea
                                    id="full_md"
                                    name="full_md"
                                    rows={14}
                                    required
                                    className="font-mono"
                                    defaultValue={prompt?.fullMd}
                                    aria-invalid={!!errors.full_md}
                                />
                                <FieldError>{errors.full_md}</FieldError>
                            </Field>
                            <Field data-invalid={!!errors.skill_keys}>
                                <FieldLabel htmlFor="skill_keys">
                                    Skills
                                </FieldLabel>
                                <Input
                                    id="skill_keys"
                                    name="skill_keys"
                                    placeholder="icp-research, positioning"
                                    defaultValue={prompt?.skillKeys}
                                />
                                <FieldDescription>
                                    Comma separated.
                                </FieldDescription>
                                <FieldError>{errors.skill_keys}</FieldError>
                            </Field>
                            <Field orientation="horizontal">
                                <Button type="submit" disabled={processing}>
                                    Save prompt
                                </Button>
                            </Field>
                        </FieldGroup>
                    )}
                </Form>
            </div>
        </>
    );
}
