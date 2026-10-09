import { Form, router } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';
import { useState } from 'react';
import ProjectPackController from '@/actions/App/Domain/Project/Http/Controllers/ProjectPackController';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { FieldError } from '@/components/ui/field';
import { Skeleton } from '@/components/ui/skeleton';
import type { AvailablePack } from '@/types';

export function AddPackDialog({
    projectSlug,
    packs,
}: {
    projectSlug: string;
    packs?: AvailablePack[];
}) {
    const [open, setOpen] = useState(false);

    const changeOpen = (value: boolean) => {
        setOpen(value);

        if (value) {
            router.reload({ only: ['availablePacks'] });
        }
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Plus data-icon="inline-start" /> Add pack
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Add a pack</DialogTitle>
                    <DialogDescription>
                        Packs add more tasks for this phase. Tasks you already
                        have stay as they are.
                    </DialogDescription>
                </DialogHeader>
                {packs === undefined ? (
                    <div className="flex flex-col gap-2">
                        <Skeleton className="h-20 w-full animate-pulse" />
                        <Skeleton className="h-20 w-full animate-pulse" />
                    </div>
                ) : packs.length === 0 ? (
                    <EmptyState
                        size="sm"
                        icon={Package}
                        title="No more packs for this phase."
                    />
                ) : (
                    <div className="flex flex-col gap-3">
                        {packs.map((pack) => (
                            <Card key={pack.key} className="gap-3 py-4">
                                <CardHeader>
                                    <CardTitle>{pack.name}</CardTitle>
                                    <CardDescription>
                                        {pack.descriptionMd ??
                                            `${pack.itemsCount} tasks`}
                                    </CardDescription>
                                </CardHeader>
                                <CardFooter className="gap-2">
                                    <Form
                                        {...ProjectPackController.store.form({
                                            project: projectSlug,
                                        })}
                                        options={{ preserveScroll: true }}
                                        onSuccess={() => setOpen(false)}
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="pack"
                                                    value={pack.key}
                                                />
                                                <Button
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Add
                                                </Button>
                                                <FieldError>
                                                    {errors.pack}
                                                </FieldError>
                                            </>
                                        )}
                                    </Form>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
