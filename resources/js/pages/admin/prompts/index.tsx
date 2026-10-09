import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { create, edit } from '@/routes/admin/prompts';
import type { AdminPromptTemplate } from '@/types';

export default function PromptsIndex({
    prompts,
}: {
    prompts: AdminPromptTemplate[];
}) {
    return (
        <>
            <Head title="Prompts" />
            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Prompt templates"
                        description="Launcher and full prompts used by catalog actions."
                    />
                    <Button size="sm" asChild>
                        <Link href={create()}>
                            <Plus /> New prompt
                        </Link>
                    </Button>
                </div>
                <Card>
                    <CardContent>
                        {prompts.length > 0 ? (
                            <ul className="divide-y">
                                {prompts.map((prompt) => (
                                    <li
                                        key={prompt.key}
                                        className="flex items-center gap-2 py-2"
                                    >
                                        <Link
                                            href={edit(prompt.key)}
                                            className="min-w-0 flex-1 truncate text-sm hover:underline"
                                        >
                                            {prompt.title}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">
                                            v{prompt.version}
                                        </span>
                                        {prompt.adminEditedAt && (
                                            <Badge variant="outline">
                                                Edited
                                            </Badge>
                                        )}
                                        <Badge variant="secondary">
                                            {prompt.target}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No prompts yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
