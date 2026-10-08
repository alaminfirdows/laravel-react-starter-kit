import { Form, Head } from '@inertiajs/react';
import DecisionController from '@/actions/App/Domain/Knowledge/Http/Controllers/DecisionController';
import Heading from '@/components/heading';
import { Markdown } from '@/components/markdown/markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Decision, ProjectPageProps } from '@/types';

export default function DecisionsIndex({
    project,
    can,
    decisions,
}: ProjectPageProps & { decisions: Decision[] }) {
    return (
        <>
            <Head title={`Decisions · ${project.name}`} />
            <div className="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-8">
                <Heading
                    title="Decisions"
                    description="What you decided and why. Recent decisions go into the project context."
                />

                {can.update && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Log a decision</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...DecisionController.store.form({
                                    project: project.slug,
                                })}
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <FieldGroup>
                                        <Field data-invalid={!!errors.title}>
                                            <FieldLabel htmlFor="title">
                                                Title
                                            </FieldLabel>
                                            <Input
                                                id="title"
                                                name="title"
                                                required
                                                maxLength={255}
                                                placeholder="Charge monthly only"
                                                aria-invalid={!!errors.title}
                                            />
                                            <FieldError>
                                                {errors.title}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={!!errors.decision_md}
                                        >
                                            <FieldLabel htmlFor="decision_md">
                                                Decision
                                            </FieldLabel>
                                            <Textarea
                                                id="decision_md"
                                                name="decision_md"
                                                required
                                                aria-invalid={
                                                    !!errors.decision_md
                                                }
                                            />
                                            <FieldError>
                                                {errors.decision_md}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={!!errors.rationale_md}
                                        >
                                            <FieldLabel htmlFor="rationale_md">
                                                Why
                                            </FieldLabel>
                                            <Textarea
                                                id="rationale_md"
                                                name="rationale_md"
                                            />
                                            <FieldError>
                                                {errors.rationale_md}
                                            </FieldError>
                                        </Field>
                                        <Field
                                            data-invalid={!!errors.revisit_on}
                                        >
                                            <FieldLabel htmlFor="revisit_on">
                                                Revisit on
                                            </FieldLabel>
                                            <Input
                                                id="revisit_on"
                                                name="revisit_on"
                                                type="date"
                                                className="w-fit"
                                            />
                                            <FieldError>
                                                {errors.revisit_on}
                                            </FieldError>
                                        </Field>
                                        <Field orientation="horizontal">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Record decision
                                            </Button>
                                        </Field>
                                    </FieldGroup>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {decisions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No decisions yet.
                    </p>
                ) : (
                    decisions.map((decision) => (
                        <Card key={decision.id}>
                            <CardHeader>
                                <CardTitle>{decision.title}</CardTitle>
                                <CardDescription className="flex flex-wrap items-center gap-2">
                                    {decision.decidedOn}
                                    {decision.owner && ` · ${decision.owner}`}
                                    {decision.source === 'claude_mcp' && (
                                        <Badge variant="outline">Claude</Badge>
                                    )}
                                    {decision.revisitOn && (
                                        <Badge variant="secondary">
                                            Revisit {decision.revisitOn}
                                        </Badge>
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Markdown source={decision.decisionMd} />
                                {decision.rationaleMd && (
                                    <div>
                                        <h3 className="text-sm font-medium">
                                            Why
                                        </h3>
                                        <Markdown
                                            source={decision.rationaleMd}
                                            className="text-muted-foreground"
                                        />
                                    </div>
                                )}
                                {decision.alternatives.length > 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Rejected:{' '}
                                        {decision.alternatives.join(' · ')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </>
    );
}
