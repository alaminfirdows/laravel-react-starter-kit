import { Form, Head, router, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import WorkspaceSettingsController from '@/actions/App/Domain/Workspace/Http/Controllers/WorkspaceSettingsController';
import {
    SettingsCard,
    SettingsCardBody,
    SettingsCardFooter,
} from '@/components/settings-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkspaceAvatar } from '@/components/workspace-avatar';
import type { WorkspaceDetails } from '@/types';

export default function General({
    workspace,
}: {
    workspace: WorkspaceDetails;
}) {
    const { workspacePermissions } = usePage().props;
    const canUpdate = workspacePermissions?.canUpdateWorkspace ?? false;

    return (
        <>
            <Head title="Workspace settings" />

            <h1 className="sr-only">Workspace settings</h1>

            <SettingsCard title="General" description="Workspace name and URL">
                <Form
                    {...WorkspaceSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="flex flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <SettingsCardBody>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={workspace.name}
                                        required
                                        maxLength={255}
                                        disabled={!canUpdate}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="slug">URL</Label>
                                    <div className="flex items-center rounded-md border border-input pl-3 focus-within:ring-2 focus-within:ring-ring/50">
                                        <span className="text-sm text-muted-foreground">
                                            {window.location.host}/
                                        </span>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            defaultValue={workspace.slug}
                                            required
                                            minLength={2}
                                            maxLength={64}
                                            pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                            title="Lowercase letters, numbers and dashes"
                                            className="border-0 pl-0 shadow-none focus-visible:ring-0"
                                            disabled={!canUpdate}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Changing the URL breaks old links.
                                    </p>
                                    <InputError message={errors.slug} />
                                </div>
                            </SettingsCardBody>
                            {canUpdate && (
                                <SettingsCardFooter helper="Visible to all workspace members.">
                                    <Button
                                        variant="primary"
                                        disabled={processing}
                                        data-test="update-workspace-button"
                                    >
                                        Save
                                    </Button>
                                </SettingsCardFooter>
                            )}
                        </>
                    )}
                </Form>
            </SettingsCard>

            <WorkspaceLogo workspace={workspace} canUpdate={canUpdate} />

            {workspacePermissions?.canDeleteWorkspace && (
                <DeleteWorkspace workspace={workspace} />
            )}
        </>
    );
}

function WorkspaceLogo({
    workspace,
    canUpdate,
}: {
    workspace: WorkspaceDetails;
    canUpdate: boolean;
}) {
    const fileInput = useRef<HTMLInputElement>(null);

    return (
        <SettingsCard title="Logo" description="PNG, JPG or WebP. Max 2 MB.">
            <SettingsCardBody className="flex-row items-center gap-4">
                <WorkspaceAvatar
                    name={workspace.name}
                    logoUrl={workspace.logoUrl}
                    className="size-16 text-lg"
                />

                {canUpdate && (
                    <Form
                        {...WorkspaceSettingsController.updateLogo.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            if (fileInput.current) {
                                fileInput.current.value = '';
                            }
                        }}
                        className="flex flex-col gap-2"
                    >
                        {({ processing, errors, submit }) => (
                            <>
                                <div className="flex gap-2">
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        name="logo"
                                        accept="image/png,image/jpeg,image/webp"
                                        className="hidden"
                                        onChange={() => submit()}
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        disabled={processing}
                                        onClick={() =>
                                            fileInput.current?.click()
                                        }
                                    >
                                        Upload logo
                                    </Button>
                                    {workspace.logoUrl && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                            onClick={() =>
                                                router.visit(
                                                    WorkspaceSettingsController.destroyLogo(),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </div>
                                <InputError message={errors.logo} />
                            </>
                        )}
                    </Form>
                )}
            </SettingsCardBody>
        </SettingsCard>
    );
}

function DeleteWorkspace({ workspace }: { workspace: WorkspaceDetails }) {
    return (
        <SettingsCard
            destructive
            title="Delete workspace"
            description="Delete this workspace, its members and its data"
        >
            <SettingsCardBody>
                <div className="flex flex-col gap-0.5 rounded-md border border-destructive/20 bg-destructive/5 p-3 text-destructive-foreground dark:bg-destructive/10">
                    <p className="text-sm font-medium">Warning</p>
                    <p className="text-sm">This cannot be undone.</p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-workspace-button"
                        >
                            Delete workspace
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Delete {workspace.name}?</DialogTitle>
                        <DialogDescription>
                            All members lose access. Type the workspace name to
                            confirm.
                        </DialogDescription>

                        <Form
                            {...WorkspaceSettingsController.destroy.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="confirm-name">
                                            Workspace name
                                        </Label>
                                        <Input
                                            id="confirm-name"
                                            name="name"
                                            placeholder={workspace.name}
                                            autoComplete="off"
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                            data-test="confirm-delete-workspace-button"
                                        >
                                            Delete workspace
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </SettingsCardBody>
        </SettingsCard>
    );
}
