import { Head, Link } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import CompetitorController from '@/actions/App/Domain/Knowledge/Http/Controllers/CompetitorController';
import InterviewController from '@/actions/App/Domain/Knowledge/Http/Controllers/InterviewController';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import Heading from '@/components/heading';
import { ResearchFormDialog } from '@/components/project/research-form-dialog';
import type { ResearchField } from '@/components/project/research-form-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { show as knowledgeShow } from '@/routes/projects/knowledge';
import type { Competitor, Interview, ProjectPageProps } from '@/types';

type ResearchIndexProps = ProjectPageProps & {
    interviews: Interview[];
    competitors: Competitor[];
};

const interviewFields: ResearchField[] = [
    { name: 'person', label: 'Person', required: true },
    { name: 'company', label: 'Company' },
    { name: 'role', label: 'Role' },
    { name: 'interviewed_on', label: 'Date', type: 'date' },
    { name: 'problem', label: 'Problem', type: 'textarea' },
    { name: 'current_solution', label: 'Current solution', type: 'textarea' },
    { name: 'pain', label: 'Pain', type: 'textarea' },
    { name: 'desired_outcome', label: 'Desired outcome', type: 'textarea' },
    { name: 'objections', label: 'Objections', type: 'textarea' },
    { name: 'quotes', label: 'Quotes', type: 'textarea' },
    { name: 'feature_requests', label: 'Feature requests', type: 'textarea' },
];

const competitorFields: ResearchField[] = [
    { name: 'name', label: 'Name', required: true },
    {
        name: 'url',
        label: 'Website',
        type: 'url',
        placeholder: 'https://example.com',
    },
    { name: 'pricing', label: 'Pricing', type: 'textarea' },
    { name: 'icp', label: 'ICP', type: 'textarea' },
    { name: 'positioning', label: 'Positioning', type: 'textarea' },
    { name: 'features', label: 'Features', type: 'textarea' },
    { name: 'integrations', label: 'Integrations', type: 'textarea' },
    { name: 'strengths', label: 'Strengths', type: 'textarea' },
    { name: 'complaints', label: 'Complaints', type: 'textarea' },
    { name: 'last_reviewed_at', label: 'Last reviewed', type: 'date' },
];

function interviewDefaults(
    interview: Interview,
): Record<string, string | null> {
    return {
        person: interview.person,
        company: interview.company,
        role: interview.role,
        interviewed_on: interview.interviewedOn,
        problem: interview.problem,
        current_solution: interview.currentSolution,
        pain: interview.pain,
        desired_outcome: interview.desiredOutcome,
        objections: interview.objections,
        quotes: interview.quotes,
        feature_requests: interview.featureRequests,
    };
}

function competitorDefaults(
    competitor: Competitor,
): Record<string, string | null> {
    return {
        name: competitor.name,
        url: competitor.url,
        pricing: competitor.pricing,
        icp: competitor.icp,
        positioning: competitor.positioning,
        features: competitor.features,
        integrations: competitor.integrations,
        strengths: competitor.strengths,
        complaints: competitor.complaints,
        last_reviewed_at: competitor.lastReviewedAt,
    };
}

export default function ResearchIndex({
    project,
    can,
    interviews,
    competitors,
}: ResearchIndexProps) {
    const projectArgs = { project: project.slug };

    return (
        <>
            <Head title={`Research · ${project.name}`} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-8">
                <Heading
                    title="Research"
                    description="Customer interviews and competitors. Each row is also saved as a knowledge document, so search finds it."
                />

                <Tabs defaultValue="interviews">
                    <TabsList>
                        <TabsTrigger value="interviews">
                            Interviews ({interviews.length})
                        </TabsTrigger>
                        <TabsTrigger value="competitors">
                            Competitors ({competitors.length})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="interviews" className="space-y-4">
                        {can.update && (
                            <ResearchFormDialog
                                trigger={
                                    <Button size="sm">
                                        <Plus /> Add interview
                                    </Button>
                                }
                                title="Add interview"
                                description="Write down what the person said, in their words."
                                submitLabel="Save interview"
                                form={InterviewController.store.form(
                                    projectArgs,
                                )}
                                fields={interviewFields}
                            />
                        )}
                        {interviews.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No interviews yet.
                            </p>
                        ) : (
                            interviews.map((interview) => (
                                <ResearchCard
                                    key={interview.id}
                                    title={interview.person}
                                    meta={[
                                        interview.role,
                                        interview.company,
                                        interview.interviewedOn,
                                    ]}
                                    documentHref={
                                        interview.knowledgeDocumentId
                                            ? knowledgeShow({
                                                  ...projectArgs,
                                                  knowledgeDocument:
                                                      interview.knowledgeDocumentId,
                                              }).url
                                            : null
                                    }
                                    sections={[
                                        ['Problem', interview.problem],
                                        ['Pain', interview.pain],
                                        [
                                            'Desired outcome',
                                            interview.desiredOutcome,
                                        ],
                                        ['Quotes', interview.quotes],
                                    ]}
                                    actions={
                                        can.update && (
                                            <>
                                                <ResearchFormDialog
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            Edit
                                                        </Button>
                                                    }
                                                    title="Edit interview"
                                                    description="Changes update the knowledge document as a new version."
                                                    submitLabel="Save interview"
                                                    form={InterviewController.update.form(
                                                        {
                                                            ...projectArgs,
                                                            interview:
                                                                interview.id,
                                                        },
                                                    )}
                                                    fields={interviewFields}
                                                    defaults={interviewDefaults(
                                                        interview,
                                                    )}
                                                />
                                                <ConfirmActionDialog
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            Delete
                                                        </Button>
                                                    }
                                                    title="Delete interview?"
                                                    description="The row is removed and its knowledge document is archived."
                                                    confirmLabel="Delete interview"
                                                    form={InterviewController.destroy.form(
                                                        {
                                                            ...projectArgs,
                                                            interview:
                                                                interview.id,
                                                        },
                                                    )}
                                                />
                                            </>
                                        )
                                    }
                                />
                            ))
                        )}
                    </TabsContent>

                    <TabsContent value="competitors" className="space-y-4">
                        {can.update && (
                            <ResearchFormDialog
                                trigger={
                                    <Button size="sm">
                                        <Plus /> Add competitor
                                    </Button>
                                }
                                title="Add competitor"
                                description="What they sell, to whom, and what their customers complain about."
                                submitLabel="Save competitor"
                                form={CompetitorController.store.form(
                                    projectArgs,
                                )}
                                fields={competitorFields}
                            />
                        )}
                        {competitors.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No competitors yet.
                            </p>
                        ) : (
                            competitors.map((competitor) => (
                                <ResearchCard
                                    key={competitor.id}
                                    title={competitor.name}
                                    meta={[
                                        competitor.url,
                                        competitor.lastReviewedAt &&
                                            `Reviewed ${competitor.lastReviewedAt}`,
                                    ]}
                                    documentHref={
                                        competitor.knowledgeDocumentId
                                            ? knowledgeShow({
                                                  ...projectArgs,
                                                  knowledgeDocument:
                                                      competitor.knowledgeDocumentId,
                                              }).url
                                            : null
                                    }
                                    sections={[
                                        ['Pricing', competitor.pricing],
                                        ['Positioning', competitor.positioning],
                                        ['Strengths', competitor.strengths],
                                        ['Complaints', competitor.complaints],
                                    ]}
                                    actions={
                                        can.update && (
                                            <>
                                                <ResearchFormDialog
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            Edit
                                                        </Button>
                                                    }
                                                    title="Edit competitor"
                                                    description="Changes update the knowledge document as a new version."
                                                    submitLabel="Save competitor"
                                                    form={CompetitorController.update.form(
                                                        {
                                                            ...projectArgs,
                                                            competitor:
                                                                competitor.id,
                                                        },
                                                    )}
                                                    fields={competitorFields}
                                                    defaults={competitorDefaults(
                                                        competitor,
                                                    )}
                                                />
                                                <ConfirmActionDialog
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            Delete
                                                        </Button>
                                                    }
                                                    title="Delete competitor?"
                                                    description="The row is removed and its knowledge document is archived."
                                                    confirmLabel="Delete competitor"
                                                    form={CompetitorController.destroy.form(
                                                        {
                                                            ...projectArgs,
                                                            competitor:
                                                                competitor.id,
                                                        },
                                                    )}
                                                />
                                            </>
                                        )
                                    }
                                />
                            ))
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

function ResearchCard({
    title,
    meta,
    documentHref,
    sections,
    actions,
}: {
    title: string;
    meta: (string | null)[];
    documentHref: string | null;
    sections: [string, string | null][];
    actions: ReactNode;
}) {
    const filledMeta = meta.filter(Boolean).join(' · ');
    const filledSections = sections.filter(([, body]) => !!body);

    return (
        <Card>
            <CardHeader className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 space-y-1.5">
                    <CardTitle>{title}</CardTitle>
                    {filledMeta && (
                        <CardDescription className="break-all">
                            {filledMeta}
                        </CardDescription>
                    )}
                </div>
                <div className="flex gap-1">
                    {documentHref && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={documentHref}>
                                <FileText /> Doc
                            </Link>
                        </Button>
                    )}
                    {actions}
                </div>
            </CardHeader>
            {filledSections.length > 0 && (
                <CardContent className="space-y-3">
                    {filledSections.map(([heading, body]) => (
                        <div key={heading}>
                            <h3 className="text-sm font-medium">{heading}</h3>
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {body}
                            </p>
                        </div>
                    ))}
                </CardContent>
            )}
        </Card>
    );
}
