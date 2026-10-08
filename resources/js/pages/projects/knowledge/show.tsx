import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import Heading from '@/components/heading';
import { DocStatusBadge } from '@/components/knowledge/doc-status-badge';
import { Markdown } from '@/components/markdown/markdown';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit } from '@/routes/projects/knowledge';
import type { KnowledgeDocument, ProjectPageProps } from '@/types';

const authors = { user: 'You', agent: 'Claude', system: 'System' } as const;

export default function KnowledgeShow({
    project,
    can,
    document,
}: ProjectPageProps & { document: KnowledgeDocument }) {
    return (
        <>
            <Head title={document.title} />
            <div className="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-8">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title={document.title}
                            description={`${document.docTypeLabel} · version ${document.version}`}
                        />
                        <div className="flex gap-2">
                            <DocStatusBadge status={document.status} />
                            {!document.isEmbedded && (
                                <Badge variant="outline">Indexing…</Badge>
                            )}
                        </div>
                    </div>
                    {can.update && (
                        <Button variant="outline" asChild>
                            <Link
                                href={edit({
                                    project: project.slug,
                                    knowledgeDocument: document.id,
                                })}
                            >
                                <Pencil /> Edit
                            </Link>
                        </Button>
                    )}
                </div>

                <Markdown source={document.bodyMd ?? null} />

                {document.versions && document.versions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Versions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {document.versions.map((version) => (
                                <div
                                    key={version.id}
                                    className="flex flex-wrap items-baseline gap-x-2"
                                >
                                    <span className="font-medium">
                                        v{version.version}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {authors[version.createdByType]}
                                        {version.createdAt &&
                                            ` · ${new Date(version.createdAt).toLocaleString()}`}
                                    </span>
                                    {version.changeNote && (
                                        <span>— {version.changeNote}</span>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
