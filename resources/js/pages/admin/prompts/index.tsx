import { Head, Link } from '@inertiajs/react';
import { MessageSquareText, Plus } from '@/components/animated-icons';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, edit } from '@/routes/admin/prompts';
import type { AdminPromptTemplate } from '@/types';

export default function PromptsIndex({
    prompts,
}: {
    prompts: AdminPromptTemplate[];
}) {
    const newPrompt = (
        <Button size="sm" asChild>
            <Link href={create()}>
                <Plus data-icon="inline-start" /> New prompt
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Prompts" />
            <Page>
                <PageHeader
                    title="Prompt templates"
                    description="Launcher and full prompts used by catalog actions."
                    actions={newPrompt}
                />
                {prompts.length > 0 ? (
                    <Card className="gap-0 py-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="pl-4">
                                        Prompt
                                    </TableHead>
                                    <TableHead>Target</TableHead>
                                    <TableHead>Version</TableHead>
                                    <TableHead className="pr-4 text-right">
                                        State
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {prompts.map((prompt) => (
                                    <TableRow key={prompt.key}>
                                        <TableCell className="py-2 pl-4">
                                            <Link
                                                href={edit(prompt.key)}
                                                className="font-medium hover:underline"
                                            >
                                                {prompt.title}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="py-2">
                                            <Badge variant="secondary">
                                                {prompt.target}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="py-2 font-mono text-xs text-muted-foreground">
                                            v{prompt.version}
                                        </TableCell>
                                        <TableCell className="py-2 pr-4 text-right">
                                            {prompt.adminEditedAt && (
                                                <Badge variant="warning">
                                                    Edited
                                                </Badge>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                ) : (
                    <EmptyState
                        icon={MessageSquareText}
                        title="No prompts yet."
                        description="Create a template for catalog actions to use."
                        action={newPrompt}
                    />
                )}
            </Page>
        </>
    );
}
