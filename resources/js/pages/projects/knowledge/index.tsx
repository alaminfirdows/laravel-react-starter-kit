import { Form, Head, Link, WhenVisible } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';
import KnowledgeController from '@/actions/App/Domain/Knowledge/Http/Controllers/KnowledgeController';
import Heading from '@/components/heading';
import { DocStatusBadge } from '@/components/knowledge/doc-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
            <div className="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-8">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Knowledge"
                        description="ICP, positioning, research and notes. Claude and your prompts read these."
                    />
                    {can.update && (
                        <Button asChild>
                            <Link href={create({ project: project.slug })}>
                                <Plus /> New document
                            </Link>
                        </Button>
                    )}
                </div>

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
                                <Search /> Search
                            </Button>
                        </>
                    )}
                </Form>

                {query !== '' && (
                    <section className="space-y-3">
                        <h2 className="font-semibold">Results for “{query}”</h2>
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
                    <Card>
                        <CardHeader>
                            <CardTitle>No documents yet</CardTitle>
                            <CardDescription>
                                Write your ICP or positioning here, or ask
                                Claude to save it with the Founder OS connector.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    groups.map(([label, items]) => (
                        <section key={label} className="space-y-2">
                            <h2 className="text-sm font-medium text-muted-foreground">
                                {label}
                            </h2>
                            <Card className="py-2">
                                <CardContent className="divide-y px-2">
                                    {items?.map((document) => (
                                        <Link
                                            key={document.id}
                                            href={show({
                                                project: project.slug,
                                                knowledgeDocument: document.id,
                                            })}
                                            className="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-muted"
                                        >
                                            <FileText className="size-4 text-muted-foreground" />
                                            <span className="min-w-0 flex-1 truncate">
                                                {document.title}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                v{document.version}
                                            </span>
                                            <DocStatusBadge
                                                status={document.status}
                                            />
                                        </Link>
                                    ))}
                                </CardContent>
                            </Card>
                        </section>
                    ))
                )}
            </div>
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
        return (
            <p className="text-sm text-muted-foreground">No matches found.</p>
        );
    }

    return (
        <div className="space-y-2">
            {results.map((result) => (
                <Link
                    key={result.chunkId}
                    href={show({
                        project: projectSlug,
                        knowledgeDocument: result.documentId,
                    })}
                    className="block rounded-md border p-3 hover:bg-muted"
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
