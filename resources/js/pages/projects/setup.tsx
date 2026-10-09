import { Form, Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from '@/components/animated-icons';
import { useState } from 'react';
import ProjectLogoController from '@/actions/App/Domain/Project/Http/Controllers/ProjectLogoController';
import ProjectSetupController from '@/actions/App/Domain/Project/Http/Controllers/ProjectSetupController';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { MarkdownEditor } from '@/components/markdown/markdown-editor';
import { SetupSteps } from '@/components/project/setup-steps';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import { index, show } from '@/routes/projects';
import { edit } from '@/routes/projects/setup';
import type {
    Option,
    ProjectGoal,
    ProjectSetup,
    ProjectSetupStep,
} from '@/types';

type SetupProps = {
    project: ProjectSetup;
    step: ProjectSetupStep;
    steps: Option<ProjectSetupStep>[];
    options: { businessModels: Option[]; stages: Option[] };
};

type Errors = Partial<Record<string, string>>;

export default function ProjectSetupPage({
    project,
    step,
    steps,
    options,
}: SetupProps) {
    const currentIndex = steps.findIndex((s) => s.value === step);
    const previous = steps[currentIndex - 1];
    const isLast = currentIndex === steps.length - 1;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: project.name, href: show({ project: project.slug }) },
            { title: 'Setup', href: edit({ project: project.slug, step }) },
        ],
    });

    const footer = (processing: boolean) => (
        <Field
            orientation="horizontal"
            className="sticky bottom-0 -mx-5 mt-2 -mb-5 flex-wrap justify-between gap-2 rounded-b-xl border-t bg-card/95 px-5 py-3 backdrop-blur"
        >
            <div className="flex flex-wrap gap-2">
                {previous && (
                    <Button variant="secondary" asChild>
                        <Link
                            href={edit({
                                project: project.slug,
                                step: previous.value,
                            })}
                        >
                            Back
                        </Link>
                    </Button>
                )}
                <Button variant="ghost" asChild>
                    <Link href={show({ project: project.slug })}>
                        Skip for now
                    </Link>
                </Button>
            </div>
            <Button variant="primary" type="submit" disabled={processing}>
                {isLast ? 'Finish setup' : 'Save and continue'}
            </Button>
        </Field>
    );

    const action = ProjectSetupController.update.form({
        project: project.slug,
        step,
    });

    return (
        <>
            <Head title={`Set up ${project.name}`} />
            <Page size="narrow">
                <PageHeader
                    title="Set up your project"
                    description="We use this to tailor tasks and prompts."
                />
                <SetupSteps
                    steps={steps}
                    current={step}
                    projectSlug={project.slug}
                />

                <Card className="px-(--card-spacing)">
                    {step === 'identity' && <LogoForm project={project} />}

                    {step === 'goals' ? (
                        <GoalsForm project={project} footer={footer} />
                    ) : (
                        <Form
                            key={step}
                            {...action}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <FieldGroup>
                                    {step === 'identity' && (
                                        <IdentityFields
                                            project={project}
                                            errors={errors}
                                        />
                                    )}
                                    {step === 'business' && (
                                        <BusinessFields
                                            project={project}
                                            options={options}
                                            errors={errors}
                                        />
                                    )}
                                    {step === 'market' && (
                                        <MarketFields
                                            project={project}
                                            errors={errors}
                                        />
                                    )}
                                    {footer(processing)}
                                </FieldGroup>
                            )}
                        </Form>
                    )}
                </Card>
            </Page>
        </>
    );
}

function LogoForm({ project }: { project: ProjectSetup }) {
    return (
        <Form
            {...ProjectLogoController.store.form({ project: project.slug })}
            options={{ preserveScroll: true }}
            resetOnSuccess
        >
            {({ processing, errors }) => (
                <Field data-invalid={!!errors.logo}>
                    <FieldLabel htmlFor="logo">Logo</FieldLabel>
                    <div className="flex items-center gap-3">
                        <WorkspaceAvatar
                            name={project.name}
                            logoUrl={project.logoUrl}
                            className="size-12"
                        />
                        <Input
                            id="logo"
                            name="logo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            aria-invalid={!!errors.logo}
                        />
                        <Button
                            type="submit"
                            variant="secondary"
                            disabled={processing}
                        >
                            Upload
                        </Button>
                    </div>
                    <FieldDescription>
                        PNG, JPG or WebP, max 2 MB.
                    </FieldDescription>
                    <FieldError>{errors.logo}</FieldError>
                </Field>
            )}
        </Form>
    );
}

function IdentityFields({
    project,
    errors,
}: {
    project: ProjectSetup;
    errors: Errors;
}) {
    const [oneLinerLength, setOneLinerLength] = useState(
        project.one_liner.length,
    );

    return (
        <>
            <Field data-invalid={!!errors.name}>
                <FieldLabel htmlFor="name">Name</FieldLabel>
                <Input
                    id="name"
                    name="name"
                    defaultValue={project.name}
                    required
                    maxLength={120}
                    aria-invalid={!!errors.name}
                />
                <FieldError>{errors.name}</FieldError>
            </Field>
            <Field data-invalid={!!errors.one_liner}>
                <FieldLabel htmlFor="one_liner">One-liner</FieldLabel>
                <Input
                    id="one_liner"
                    name="one_liner"
                    defaultValue={project.one_liner}
                    required
                    maxLength={140}
                    placeholder="What you do, for whom, in one sentence."
                    onChange={(e) => setOneLinerLength(e.target.value.length)}
                    aria-invalid={!!errors.one_liner}
                />
                <FieldDescription>{oneLinerLength}/140</FieldDescription>
                <FieldError>{errors.one_liner}</FieldError>
            </Field>
            <Field data-invalid={!!errors.description_md}>
                <FieldLabel htmlFor="description_md">Description</FieldLabel>
                <MarkdownEditor
                    id="description_md"
                    name="description_md"
                    defaultValue={project.description_md}
                    placeholder="Longer story: background, product, traction…"
                />
                <FieldError>{errors.description_md}</FieldError>
            </Field>
            <Field data-invalid={!!errors.website_url}>
                <FieldLabel htmlFor="website_url">Website</FieldLabel>
                <Input
                    id="website_url"
                    name="website_url"
                    type="url"
                    defaultValue={project.website_url}
                    placeholder="https://"
                    aria-invalid={!!errors.website_url}
                />
                <FieldError>{errors.website_url}</FieldError>
            </Field>
        </>
    );
}

function BusinessFields({
    project,
    options,
    errors,
}: {
    project: ProjectSetup;
    options: SetupProps['options'];
    errors: Errors;
}) {
    return (
        <>
            <Field data-invalid={!!errors.business_model}>
                <FieldLabel htmlFor="business_model">Business model</FieldLabel>
                <Select
                    name="business_model"
                    items={options.businessModels}
                    defaultValue={project.business_model || undefined}
                >
                    <SelectTrigger
                        id="business_model"
                        className="w-full"
                        aria-invalid={!!errors.business_model}
                    >
                        <SelectValue placeholder="Choose a model" />
                    </SelectTrigger>
                </Select>
                <FieldError>{errors.business_model}</FieldError>
            </Field>
            <Field data-invalid={!!errors.stage}>
                <FieldLabel htmlFor="stage">Stage</FieldLabel>
                <Select
                    name="stage"
                    items={options.stages}
                    defaultValue={project.stage || undefined}
                >
                    <SelectTrigger
                        id="stage"
                        className="w-full"
                        aria-invalid={!!errors.stage}
                    >
                        <SelectValue placeholder="Choose a stage" />
                    </SelectTrigger>
                </Select>
                <FieldError>{errors.stage}</FieldError>
            </Field>
            <Field data-invalid={!!errors.industry}>
                <FieldLabel htmlFor="industry">Industry</FieldLabel>
                <Input
                    id="industry"
                    name="industry"
                    defaultValue={project.industry}
                    maxLength={100}
                    placeholder="e.g. HR tech"
                />
                <FieldError>{errors.industry}</FieldError>
            </Field>
            <Field data-invalid={!!errors.pricing_model}>
                <FieldLabel htmlFor="pricing_model">Pricing model</FieldLabel>
                <Input
                    id="pricing_model"
                    name="pricing_model"
                    defaultValue={project.pricing_model}
                    maxLength={100}
                    placeholder="e.g. per seat, monthly"
                />
                <FieldError>{errors.pricing_model}</FieldError>
            </Field>
        </>
    );
}

function MarketFields({
    project,
    errors,
}: {
    project: ProjectSetup;
    errors: Errors;
}) {
    const textareas = [
        {
            name: 'target_customer',
            label: 'Target customer',
            placeholder: 'Who buys and who uses it?',
        },
        {
            name: 'problem_statement',
            label: 'Problem',
            placeholder: 'What pain do they have today?',
        },
        {
            name: 'solution_summary',
            label: 'Solution',
            placeholder: 'How do you solve it?',
        },
    ] as const;

    return (
        <>
            <Field data-invalid={!!errors.primary_market}>
                <FieldLabel htmlFor="primary_market">Primary market</FieldLabel>
                <Input
                    id="primary_market"
                    name="primary_market"
                    defaultValue={project.primary_market}
                    required
                    minLength={2}
                    maxLength={2}
                    placeholder="US"
                    className="w-24 uppercase"
                    aria-invalid={!!errors.primary_market}
                />
                <FieldDescription>Two-letter country code.</FieldDescription>
                <FieldError>{errors.primary_market}</FieldError>
            </Field>
            {textareas.map((field) => (
                <Field key={field.name} data-invalid={!!errors[field.name]}>
                    <FieldLabel htmlFor={field.name}>{field.label}</FieldLabel>
                    <Textarea
                        id={field.name}
                        name={field.name}
                        defaultValue={project[field.name]}
                        placeholder={field.placeholder}
                        aria-invalid={!!errors[field.name]}
                    />
                    <FieldError>{errors[field.name]}</FieldError>
                </Field>
            ))}
        </>
    );
}

const MAX_GOALS = 10;

type GoalRow = Record<keyof ProjectGoal, string>;

const emptyGoal: GoalRow = {
    title: '',
    metric: '',
    target: '',
    due: '',
};

function GoalsForm({
    project,
    footer,
}: {
    project: ProjectSetup;
    footer: (processing: boolean) => React.ReactNode;
}) {
    const form = useForm<{ goals: GoalRow[] }>({
        goals: project.goals.map((goal) => ({
            title: goal.title,
            metric: goal.metric ?? '',
            target: goal.target ?? '',
            due: goal.due ?? '',
        })),
    });
    const errors = form.errors as Errors;

    const setGoal = (i: number, key: keyof ProjectGoal, value: string) =>
        form.setData(
            'goals',
            form.data.goals.map((goal, j) =>
                j === i ? { ...goal, [key]: value } : goal,
            ),
        );

    const goalFields = [
        {
            key: 'title',
            label: 'Goal',
            type: 'text',
            placeholder: 'First 10 paying customers',
        },
        {
            key: 'metric',
            label: 'Metric',
            type: 'text',
            placeholder: 'Paying customers',
        },
        { key: 'target', label: 'Target', type: 'text', placeholder: '10' },
        { key: 'due', label: 'Due', type: 'date', placeholder: '' },
    ] as const;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.submit(
                    ProjectSetupController.update({
                        project: project.slug,
                        step: 'goals',
                    }),
                    { preserveScroll: true },
                );
            }}
        >
            <FieldGroup>
                {form.data.goals.length === 0 && (
                    <FieldDescription>
                        No goals yet. Goals are optional, but they focus your
                        plan.
                    </FieldDescription>
                )}
                {form.data.goals.map((goal, i) => (
                    <div
                        key={i}
                        className="grid items-start gap-3 rounded-lg border p-4 md:grid-cols-[2fr_1fr_1fr_1fr_auto]"
                    >
                        {goalFields.map((field) => {
                            const error = errors[`goals.${i}.${field.key}`];

                            return (
                                <Field key={field.key} data-invalid={!!error}>
                                    <FieldLabel
                                        htmlFor={`goal-${i}-${field.key}`}
                                    >
                                        {field.label}
                                    </FieldLabel>
                                    <Input
                                        id={`goal-${i}-${field.key}`}
                                        type={field.type}
                                        value={goal[field.key]}
                                        required={field.key === 'title'}
                                        placeholder={field.placeholder}
                                        onChange={(e) =>
                                            setGoal(
                                                i,
                                                field.key,
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={!!error}
                                    />
                                    <FieldError>{error}</FieldError>
                                </Field>
                            );
                        })}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="md:mt-6"
                            aria-label="Remove goal"
                            onClick={() =>
                                form.setData(
                                    'goals',
                                    form.data.goals.filter((_, j) => j !== i),
                                )
                            }
                        >
                            <Trash2 />
                        </Button>
                    </div>
                ))}
                <FieldError>{errors.goals}</FieldError>
                {form.data.goals.length < MAX_GOALS && (
                    <Field orientation="horizontal">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() =>
                                form.setData('goals', [
                                    ...form.data.goals,
                                    { ...emptyGoal },
                                ])
                            }
                        >
                            <Plus data-icon="inline-start" /> Add goal
                        </Button>
                    </Field>
                )}
                {footer(form.processing)}
            </FieldGroup>
        </form>
    );
}
