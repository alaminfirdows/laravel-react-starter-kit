import { Form, Head, Link } from '@inertiajs/react';
import { Info } from '@/components/animated-icons';
import CommunityPackController from '@/actions/App/Domain/Catalog/Http/Controllers/CommunityPackController';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { PackReviewBadge } from '@/components/pack/pack-review-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/packs';
import type { CommunityPack, Option, PackRootTask, PhaseOption } from '@/types';

type Props = {
    pack: CommunityPack | null;
    phaseOptions: PhaseOption[];
    visibilityOptions: Option[];
    rootTasks: PackRootTask[];
};

export default function CommunityPackEdit({
    pack,
    phaseOptions,
    visibilityOptions,
    rootTasks,
}: Props) {
    const form = pack
        ? CommunityPackController.update.form({ pack: pack.key })
        : CommunityPackController.store.form();
    const selected = new Set(pack?.items?.map((item) => item.key) ?? []);

    return (
        <>
            <Head title={pack ? `Edit ${pack.name}` : 'New pack'} />
            <Page size="narrow">
                <PageHeader
                    title={pack ? pack.name : 'New pack'}
                    description="Pick catalog tasks. Each one comes with its subtasks."
                    actions={
                        <>
                            {pack && <PackReviewBadge pack={pack} />}
                            <Button variant="secondary" asChild>
                                <Link href={index()}>Back</Link>
                            </Button>
                        </>
                    }
                />
                {pack?.reviewStatus === 'rejected' && pack.reviewNote && (
                    <Alert variant="destructive">
                        <Info />
                        <AlertTitle>Review feedback</AlertTitle>
                        <AlertDescription>{pack.reviewNote}</AlertDescription>
                    </Alert>
                )}
                <Form {...form} options={{ preserveScroll: true }}>
                    {({ processing, errors }) => (
                        <FieldGroup>
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
                            <div className="grid gap-4 sm:grid-cols-2">
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
                                <Field data-invalid={!!errors.visibility}>
                                    <FieldLabel htmlFor="visibility">
                                        Visibility
                                    </FieldLabel>
                                    <Select
                                        name="visibility"
                                        items={visibilityOptions}
                                        defaultValue={
                                            pack?.visibility ?? 'private'
                                        }
                                    >
                                        <SelectTrigger
                                            id="visibility"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                    </Select>
                                    <FieldDescription>
                                        Public packs are reviewed before other
                                        workspaces see them.
                                    </FieldDescription>
                                    <FieldError>{errors.visibility}</FieldError>
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
                            <FieldSet data-invalid={!!errors.items}>
                                <FieldLegend variant="label">Tasks</FieldLegend>
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
                                        </Field>
                                    ))}
                                </div>
                                <FieldError>{errors.items}</FieldError>
                            </FieldSet>
                            <Field orientation="horizontal">
                                <Button variant="primary" type="submit" disabled={processing}>
                                    Save pack
                                </Button>
                            </Field>
                        </FieldGroup>
                    )}
                </Form>
            </Page>
        </>
    );
}
