import { Form, Head, Link, WhenVisible } from '@inertiajs/react';
import { FileText, Plus, Search } from '@/components/animated-icons';
import KnowledgeController from '@/actions/App/Domain/Knowledge/Http/Controllers/KnowledgeController';
import { EmptyState } from '@/components/empty-state';
import { DocStatusBadge } from '@/components/knowledge/doc-status-badge';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { create, show } from '@/routes/projects/knowledge';
import type {
    KnowledgeDocument,
    KnowledgeSearchResult,
    ProjectPageProps,
} from '@/types';

type KnowledgeIndexProps = ProjectPageProps & {
    documents: KnowledgeDocument[];
    query: string;
    results?: KnowledgeSearchResult[];
};

export default function KnowledgeIndex({
    project,
    can,
    documents,
    query,
    results,
}: KnowledgeIndexProps) {
    const groups = Object.entries(
        Object.groupBy(documents, (document) => document.docTypeLabel),
    );

    return (
        <>
            <Head title={`Knowledge · ${project.name}`} />
            <Page>
                <PageHeader
                    title="Knowledge"
                    description="ICP, positioning, research and notes. Claude and your prompts read these."
                    actions={
                        can.update && (
                            <Button asChild>
                                <Link href={create({ project: project.slug })}>
                                    <Plus data-icon="inline-start" /> New
                                    document
                                </Link>
                            </Button>
                        )
                    }
                />

                <Form
                    {...KnowledgeController.index.form({
                        project: project.slug,
                    })}
                    options={{
                        only: ['results', 'query'],
                        preserveState: true,
                        replace: true,
                    }}
                    className="flex gap-2"
                >
                    {({ processing }) => (
                        <>
                            <Input
                                name="q"
                                type="search"
                                defaultValue={query}
                                placeholder="Search knowledge, e.g. why do customers churn?"
                                aria-label="Search knowledge"
                            />
                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={processing}
                            >
                                <Search data-icon="inline-start" /> Search
                            </Button>
                        </>
                    )}
                </Form>

                {query !== '' && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-sm font-semibold">
                            Results for “{query}”
                        </h2>
                        {results === undefined ? (
                            <WhenVisible
                                data="results"
                                fallback={<Skeleton className="h-24 w-full" />}
                            >
                                <></>
                            </WhenVisible>
                        ) : (
                            <SearchResults
                                results={results}
                                projectSlug={project.slug}
                            />
                        )}
                    </section>
                )}

                {groups.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="No documents yet"
                        description="Write your ICP or positioning here, or ask Claude to save it with the Founder OS connector."
                        action={
                            can.update && (
                                <Button size="sm" asChild>
                                    <Link
                                        href={create({ project: project.slug })}
                                    >
                                        <Plus data-icon="inline-start" /> New
                                        document
                                    </Link>
                                </Button>
                            )
                        }
                    />
                ) : (
                    groups.map(([label, items]) => (
                        <section key={label} className="flex flex-col gap-2">
                            <h2 className="text-sm font-semibold">{label}</h2>
                            <Card className="gap-0 py-0">
                                {items?.map((document) => (
                                    <Link
                                        key={document.id}
                                        href={show({
                                            project: project.slug,
                                            knowledgeDocument: document.id,
                                        })}
                                        className="flex items-center gap-3 border-b px-4 py-3 transition-colors last:border-b-0 hover:bg-muted/50"
                                    >
                                        <FileText className="size-4 shrink-0 text-muted-foreground" />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                            {document.title}
                                        </span>
                                        <span className="font-mono text-xs text-muted-foreground">
                                            v{document.version}
                                        </span>
                                        <DocStatusBadge
                                            status={document.status}
                                        />
                                    </Link>
                                ))}
                            </Card>
                        </section>
                    ))
                )}
            </Page>
        </>
    );
}

function SearchResults({
    results,
    projectSlug,
}: {
    results: KnowledgeSearchResult[];
    projectSlug: string;
}) {
    if (results.length === 0) {
        return <EmptyState size="sm" icon={Search} title="No matches found" />;
    }

    return (
        <div className="flex flex-col gap-2">
            {results.map((result) => (
                <Link
                    key={result.chunkId}
                    href={show({
                        project: projectSlug,
                        knowledgeDocument: result.documentId,
                    })}
                    className="block rounded-lg border bg-card px-4 py-3 transition-colors hover:bg-muted/50"
                >
                    <div className="text-sm font-medium">
                        {result.documentTitle}
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            · {result.docTypeLabel}
                            {result.headingPath && ` › ${result.headingPath}`}
                        </span>
                    </div>
                    <p className="mt-1 line-clamp-3 text-sm text-muted-foreground">
                        {result.snippet}
                    </p>
                </Link>
            ))}
        </div>
    );
}
