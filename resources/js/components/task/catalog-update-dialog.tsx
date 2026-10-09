import { Form, router } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import TaskCatalogUpgradeController from '@/actions/App/Domain/Task/Http/Controllers/TaskCatalogUpgradeController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import type { CatalogFieldDiff } from '@/types';

function Value({ title, text }: { title: string; text: string }) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            <p className="text-xs text-muted-foreground">{title}</p>
            <pre className="max-h-40 overflow-auto rounded-md bg-muted p-2 text-xs whitespace-pre-wrap">
                {text || '—'}
            </pre>
        </div>
    );
}

export function CatalogUpdateDialog({
    taskId,
    projectSlug,
    diff,
}: {
    taskId: string;
    projectSlug: string;
    diff?: CatalogFieldDiff[];
}) {
    const [open, setOpen] = useState(false);

    const changeOpen = (value: boolean) => {
        setOpen(value);

        if (value) {
            router.reload({ only: ['catalogDiff'] });
        }
    };

    return (
        <Alert>
            <Sparkles />
            <AlertTitle>Update available</AlertTitle>
            <AlertDescription className="flex flex-wrap items-center justify-between gap-2">
                The catalog has a newer version of this task.
                <Dialog open={open} onOpenChange={changeOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm" variant="outline">
                            Review changes
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>Update from catalog</DialogTitle>
                            <DialogDescription>
                                Pick the fields to take from the catalog. Fields
                                you changed stay yours unless you pick them.
                            </DialogDescription>
                        </DialogHeader>
                        {diff === undefined ? (
                            <div className="flex flex-col gap-2">
                                <Skeleton className="h-16 w-full" />
                                <Skeleton className="h-16 w-full" />
                            </div>
                        ) : (
                            <Form
                                {...TaskCatalogUpgradeController.store.form({
                                    project: projectSlug,
                                    task: taskId,
                                })}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setOpen(false)}
                            >
                                {({ processing, errors }) => (
                                    <FieldGroup>
                                        {diff.length === 0 && (
                                            <FieldDescription>
                                                No field changes. Accept to mark
                                                the task as up to date.
                                            </FieldDescription>
                                        )}
                                        {diff.map((item) => (
                                            <Field
                                                key={item.field}
                                                orientation="horizontal"
                                            >
                                                <Checkbox
                                                    id={`field_${item.field}`}
                                                    name="fields[]"
                                                    value={item.field}
                                                    defaultChecked={
                                                        !item.isConflict
                                                    }
                                                />
                                                <FieldContent>
                                                    <FieldLabel
                                                        htmlFor={`field_${item.field}`}
                                                    >
                                                        {item.label}
                                                        {item.isConflict && (
                                                            <Badge variant="destructive">
                                                                You changed this
                                                            </Badge>
                                                        )}
                                                    </FieldLabel>
                                                    <div className="grid gap-2 sm:grid-cols-2">
                                                        <Value
                                                            title="Yours"
                                                            text={item.founder}
                                                        />
                                                        <Value
                                                            title="Catalog"
                                                            text={item.catalog}
                                                        />
                                                    </div>
                                                </FieldContent>
                                            </Field>
                                        ))}
                                        <FieldError>{errors.fields}</FieldError>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Apply update
                                            </Button>
                                        </DialogFooter>
                                    </FieldGroup>
                                )}
                            </Form>
                        )}
                    </DialogContent>
                </Dialog>
            </AlertDescription>
        </Alert>
    );
}
