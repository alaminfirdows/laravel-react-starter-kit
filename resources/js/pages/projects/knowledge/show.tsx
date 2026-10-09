import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { DocStatusBadge } from '@/components/knowledge/doc-status-badge';
import { Markdown } from '@/components/markdown/markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import { edit } from '@/routes/projects/knowledge';
import type { KnowledgeDocument, ProjectPageProps } from '@/types';

const authors = { user: 'You', agent: 'Claude', system: 'System' } as const;

export default function KnowledgeShow({
    project,
    can,
    document,
    canEditDocument,
}: ProjectPageProps & {
    document: KnowledgeDocument;
    canEditDocument: boolean;
}) {
    return (
        <>
            <Head title={document.title} />
            <Page size="narrow">
                <PageHeader
                    title={document.title}
                    meta={
                        <>
                            <span className="font-mono text-xs text-muted-foreground">
                                {document.docTypeLabel} · v{document.version}
                            </span>
                            <DocStatusBadge status={document.status} />
                            {!document.isEmbedded && (
                                <Badge variant="outline">Indexing…</Badge>
                            )}
                        </>
                    }
                    actions={
                        can.update &&
                        canEditDocument && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={edit({
                                        project: project.slug,
                                        knowledgeDocument: document.id,
                                    })}
                                >
                                    <Pencil data-icon="inline-start" /> Edit
                                </Link>
                            </Button>
                        )
                    }
                />

                <Markdown source={document.bodyMd ?? null} />

                {document.versions && document.versions.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-sm font-semibold">Versions</h2>
                        <Card className="gap-0 py-0">
                            {document.versions.map((version) => (
                                <div
                                    key={version.id}
                                    className="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b px-4 py-3 text-sm last:border-b-0"
                                >
                                    <span className="font-mono text-xs font-medium">
                                        v{version.version}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {authors[version.createdByType]}
                                    </span>
                                    {version.createdAt && (
                                        <time
                                            dateTime={version.createdAt}
                                            title={formatDateTime(
                                                version.createdAt,
                                            )}
                                            className="font-mono text-xs text-muted-foreground"
                                        >
                                            {formatRelativeTime(
                                                version.createdAt,
                                            )}
                                        </time>
                                    )}
                                    {version.changeNote && (
                                        <span>— {version.changeNote}</span>
                                    )}
                                </div>
                            ))}
                        </Card>
                    </section>
                )}
            </Page>
        </>
    );
}
