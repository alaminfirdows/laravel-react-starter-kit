import { Form, Head } from '@inertiajs/react';
import { Inbox } from '@/components/animated-icons';
import CommunityPackReviewController from '@/actions/App/Domain/Catalog/Http/Controllers/Admin/CommunityPackReviewController';
import { EmptyState } from '@/components/empty-state';
import { Page } from '@/components/page';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import type { CommunityPack } from '@/types';

export default function CommunityPackReviews({
    packs,
}: {
    packs: CommunityPack[];
}) {
    return (
        <>
            <Head title="Community packs" />
            <Page size="narrow">
                <PageHeader
                    title="Community packs"
                    description="Public packs waiting for review. Approved packs are listed for every workspace."
                />
                {packs.length === 0 && (
                    <EmptyState
                        icon={Inbox}
                        title="Nothing to review."
                        description="New public packs will show up here."
                    />
                )}
                {packs.map((pack) => (
                    <Card key={pack.key}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {pack.name}
                                {pack.phase && (
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {pack.phase}
                                    </Badge>
                                )}
                            </CardTitle>
                            <CardDescription>
                                By {pack.owner} ·{' '}
                                <span className="font-mono">
                                    v{pack.version}
                                </span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2 text-sm">
                            {pack.descriptionMd && <p>{pack.descriptionMd}</p>}
                            <ul className="list-disc pl-5 text-muted-foreground">
                                {pack.items?.map((item) => (
                                    <li key={item.key}>{item.title}</li>
                                ))}
                            </ul>
                        </CardContent>
                        <CardFooter>
                            <Form
                                {...CommunityPackReviewController.store.form(
                                    pack.key,
                                )}
                                options={{ preserveScroll: true }}
                                className="flex w-full flex-col gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Field data-invalid={!!errors.note}>
                                            <FieldLabel
                                                htmlFor={`note-${pack.key}`}
                                            >
                                                Note (required to reject)
                                            </FieldLabel>
                                            <Textarea
                                                id={`note-${pack.key}`}
                                                name="note"
                                                rows={2}
                                            />
                                            <FieldError>
                                                {errors.note ?? errors.decision}
                                            </FieldError>
                                        </Field>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                name="decision"
                                                value="approved"
                                                disabled={processing}
                                            >
                                                Approve
                                            </Button>
                                            <Button
                                                name="decision"
                                                value="rejected"
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                Reject
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardFooter>
                    </Card>
                ))}
            </Page>
        </>
    );
}
