import { Form, Head } from '@inertiajs/react';
import { Gavel } from 'lucide-react';
import DecisionController from '@/actions/App/Domain/Knowledge/Http/Controllers/DecisionController';
import { EmptyState } from '@/components/empty-state';
import { Markdown } from '@/components/markdown/markdown';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
            <Page size="narrow">
                <PageHeader
                    title="Decisions"
                    description="What you decided and why. Recent decisions go into the project context."
                />

                {can.update && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Log a decision
                            </CardTitle>
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
                                                variant="primary"
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
                    <EmptyState
                        icon={Gavel}
                        size="sm"
                        title="No decisions yet."
                        description="Log what you decided and why."
                    />
                ) : (
                    <div className="flex flex-col gap-3">
                        {decisions.map((decision) => (
                            <Card key={decision.id} className="gap-0 py-0">
                                <div className="flex flex-col gap-1 border-b px-4 py-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-sm font-semibold">
                                            {decision.title}
                                        </h2>
                                        {decision.source === 'claude_mcp' && (
                                            <Badge variant="outline">
                                                Claude
                                            </Badge>
                                        )}
                                        {decision.revisitOn && (
                                            <Badge variant="warning">
                                                Revisit {decision.revisitOn}
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="font-mono text-xs text-muted-foreground">
                                        {decision.decidedOn}
                                        {decision.owner &&
                                            ` · ${decision.owner}`}
                                    </p>
                                </div>
                                <div className="flex flex-col gap-3 px-4 py-3">
                                    <Markdown source={decision.decisionMd} />
                                    {decision.rationaleMd && (
                                        <div className="flex flex-col gap-1">
                                            <h3 className="text-xs font-medium text-muted-foreground">
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
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </Page>
        </>
    );
}
