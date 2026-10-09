import { Form, Head } from '@inertiajs/react';
import ProjectController from '@/actions/App/Domain/Project/Http/Controllers/ProjectController';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { PhasePicker } from '@/components/project/phase-picker';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { create, index } from '@/routes/projects';
import type { PhaseOption } from '@/types';

export default function CreateProject({ phases }: { phases: PhaseOption[] }) {
    return (
        <>
            <Head title="New project" />
            <Page size="narrow">
                <PageHeader
                    title="New project"
                    description="Where are you right now? We build your plan from this."
                />
                <Card>
                    <Form {...ProjectController.store.form()}>
                        {({ processing, errors }) => (
                            <FieldGroup>
                                <FieldSet>
                                    <FieldLegend>Phase</FieldLegend>
                                    <PhasePicker phases={phases} name="phase" />
                                    <FieldError>{errors.phase}</FieldError>
                                </FieldSet>
                                <Field data-invalid={!!errors.name}>
                                    <FieldLabel htmlFor="name">
                                        Project name
                                    </FieldLabel>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        maxLength={120}
                                        placeholder="Acme Rockets"
                                        aria-invalid={!!errors.name}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field orientation="horizontal">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="create-project-button"
                                    >
                                        Create draft project
                                    </Button>
                                </Field>
                            </FieldGroup>
                        )}
                    </Form>
                </Card>
            </Page>
        </>
    );
}

CreateProject.layout = {
    breadcrumbs: [
        { title: 'Projects', href: index() },
        { title: 'New', href: create() },
    ],
};
