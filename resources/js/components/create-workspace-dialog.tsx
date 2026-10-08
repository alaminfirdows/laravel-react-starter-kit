import { Form } from '@inertiajs/react';
import WorkspaceController from '@/actions/App/Domain/Workspace/Http/Controllers/WorkspaceController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function CreateWorkspaceDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create workspace</DialogTitle>
                    <DialogDescription>
                        A workspace keeps your team, data and settings together.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...WorkspaceController.store.form()}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="workspace-name">Name</Label>
                                <Input
                                    id="workspace-name"
                                    name="name"
                                    required
                                    autoFocus
                                    maxLength={255}
                                    placeholder="Acme Inc."
                                />
                                <InputError message={errors.name} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="create-workspace-button"
                                >
                                    Create workspace
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
