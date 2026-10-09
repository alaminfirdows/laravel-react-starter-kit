import { Form, Head, Link } from '@inertiajs/react';
import PackController from '@/actions/App/Domain/Catalog/Http/Controllers/Admin/PackController';
import { CatalogStatusBadge } from '@/components/admin/catalog-status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/packs';
import type { AdminPack, Option, PackRootTask, PhaseOption } from '@/types';

type Props = {
    pack: AdminPack | null;
    phaseOptions: PhaseOption[];
    statusOptions: Option[];
    rootTasks: PackRootTask[];
};

export default function PackEdit({
    pack,
    phaseOptions,
    statusOptions,
    rootTasks,
}: Props) {
    const form = pack
        ? PackController.update.form(pack.key)
        : PackController.store.form();
    const selected = new Set(pack?.items ?? []);

    return (
        <>
            <Head title={pack ? `Edit ${pack.name}` : 'New pack'} />
            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title={pack ? pack.name : 'New pack'}
                        description={
                            pack ? `Version ${pack.version}` : undefined
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
                                        name={pack ? undefined : 'key'}
                                        required
                                        disabled={!!pack}
                                        defaultValue={pack?.key}
                                        aria-invalid={!!errors.key}
                                    />
                                    <FieldError>{errors.key}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.name}>
                                    <FieldLabel htmlFor="name">Name</FieldLabel>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={pack?.name}
                                        aria-invalid={!!errors.name}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.phase}>
                                    <FieldLabel htmlFor="phase">
                                        Phase
                                    </FieldLabel>
                                    <Select
                                        name="phase"
                                        items={phaseOptions}
                                        defaultValue={pack?.phase ?? undefined}
                                    >
                                        <SelectTrigger
                                            id="phase"
                                            className="w-full"
                                            aria-invalid={!!errors.phase}
                                        >
                                            <SelectValue placeholder="Choose a phase" />
                                        </SelectTrigger>
                                    </Select>
                                    <FieldError>{errors.phase}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.status}>
                                    <FieldLabel htmlFor="status">
                                        Status
                                    </FieldLabel>
                                    <Select
                                        name="status"
                                        items={statusOptions}
                                        defaultValue={pack?.status ?? 'draft'}
                                    >
                                        <SelectTrigger
                                            id="status"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                    </Select>
                                    <FieldError>{errors.status}</FieldError>
                                </Field>
                            </div>
                            <Field data-invalid={!!errors.description_md}>
                                <FieldLabel htmlFor="description_md">
                                    Description
                                </FieldLabel>
                                <Textarea
                                    id="description_md"
                                    name="description_md"
                                    rows={3}
                                    defaultValue={pack?.descriptionMd ?? ''}
                                />
                                <FieldError>{errors.description_md}</FieldError>
                            </Field>
                            <Field orientation="horizontal">
                                <Checkbox
                                    id="is_default"
                                    name="is_default"
                                    value="1"
                                    defaultChecked={pack?.isDefault}
                                />
                                <FieldLabel htmlFor="is_default">
                                    Default pack for this phase
                                </FieldLabel>
                            </Field>
                            <FieldSet data-invalid={!!errors.items}>
                                <FieldLegend variant="label">
                                    Tasks (with subtasks)
                                </FieldLegend>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {rootTasks.map((task) => (
                                        <Field
                                            key={task.key}
                                            orientation="horizontal"
                                        >
                                            <Checkbox
                                                id={`item-${task.key}`}
                                                name="items[]"
                                                value={task.key}
                                                defaultChecked={selected.has(
                                                    task.key,
                                                )}
                                            />
                                            <FieldLabel
                                                htmlFor={`item-${task.key}`}
                                                className="font-normal"
                                            >
                                                <span className="text-muted-foreground">
                                                    {task.category} ·
                                                </span>
                                                {task.title}
                                            </FieldLabel>
                                            {task.status !== 'published' && (
                                                <CatalogStatusBadge
                                                    status={task.status}
                                                />
                                            )}
                                        </Field>
                                    ))}
                                </div>
                                <FieldError>{errors.items}</FieldError>
                            </FieldSet>
                            <Field orientation="horizontal">
                                <Button type="submit" disabled={processing}>
                                    Save pack
                                </Button>
                            </Field>
                        </FieldGroup>
                    )}
                </Form>
            </div>
        </>
    );
}
