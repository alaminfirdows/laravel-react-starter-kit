import { Form, Head } from '@inertiajs/react';
import CommunityPackReviewController from '@/actions/App/Domain/Catalog/Http/Controllers/Admin/CommunityPackReviewController';
import Heading from '@/components/heading';
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
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Community packs"
                    description="Public packs waiting for review. Approved packs are listed for every workspace."
                />
                {packs.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Nothing to review.
                    </p>
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
                                By {pack.owner} · v{pack.version}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {pack.descriptionMd && <p>{pack.descriptionMd}</p>}
                            <ul className="list-disc pl-5">
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
                                className="w-full space-y-3"
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
                                        <div className="flex gap-2">
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
            </div>
        </>
    );
}
