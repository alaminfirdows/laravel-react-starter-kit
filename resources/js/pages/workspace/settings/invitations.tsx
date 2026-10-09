import { Form, Head, router, usePage } from '@inertiajs/react';
import WorkspaceInvitationController from '@/actions/App/Domain/Workspace/Http/Controllers/WorkspaceInvitationController';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { SettingsCard, SettingsCardBody } from '@/components/settings-card';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RoleOption, WorkspaceInvitation } from '@/types';

export default function Invitations({
    invitations,
    assignableRoles,
}: {
    invitations: WorkspaceInvitation[];
    assignableRoles: RoleOption[];
}) {
    const { workspacePermissions } = usePage().props;
    const defaultRole =
        assignableRoles.find((role) => role.value === 'member')?.value ??
        assignableRoles[0]?.value;

    return (
        <>
            <Head title="Invitations" />

            {workspacePermissions?.canCreateInvitation && (
                <SettingsCard
                    title="Invite member"
                    description="The link in the email is valid for 3 days"
                >
                    <SettingsCardBody>
                        <Form
                            {...WorkspaceInvitationController.store.form()}
                            options={{ preserveScroll: true }}
                            resetOnSuccess={['email']}
                            className="flex flex-col gap-4 sm:flex-row sm:items-start"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid flex-1 gap-2">
                                        <Label
                                            htmlFor="email"
                                            className="sr-only"
                                        >
                                            Email
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            placeholder="name@example.com"
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Select
                                            name="role"
                                            defaultValue={defaultRole}
                                        >
                                            <SelectTrigger
                                                className="w-32"
                                                aria-label="Role"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {assignableRoles.map((role) => (
                                                    <SelectItem
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.role} />
                                    </div>
                                    <Button
                                        variant="primary"
                                        disabled={processing}
                                        data-test="invite-member-button"
                                    >
                                        Send invite
                                    </Button>
                                </>
                            )}
                        </Form>
                    </SettingsCardBody>
                </SettingsCard>
            )}

            <SettingsCard
                title="Pending invitations"
                description="Invitations not accepted yet"
            >
                <SettingsCardBody>
                    {!workspacePermissions?.canCreateInvitation &&
                    !workspacePermissions?.canCancelInvitation ? (
                        <p className="text-sm text-muted-foreground">
                            Only admins can see pending invitations.
                        </p>
                    ) : invitations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No pending invitations.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.code}
                                    className="flex flex-wrap items-center gap-3 p-4"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {invitation.email}
                                        </p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {invitation.inviterName &&
                                                `Invited by ${invitation.inviterName} · `}
                                            {invitation.isExpired
                                                ? 'Expired'
                                                : `Expires ${formatDate(invitation.expiresAt)}`}
                                        </p>
                                    </div>

                                    <Badge
                                        variant={
                                            invitation.isExpired
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {invitation.roleLabel}
                                    </Badge>

                                    {workspacePermissions?.canCreateInvitation && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.visit(
                                                    WorkspaceInvitationController.resend(
                                                        {
                                                            invitation:
                                                                invitation.code,
                                                        },
                                                    ),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Resend
                                        </Button>
                                    )}

                                    {workspacePermissions?.canCancelInvitation && (
                                        <ConfirmActionDialog
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    Cancel
                                                </Button>
                                            }
                                            title="Cancel invitation?"
                                            description={`The link sent to ${invitation.email} stops working.`}
                                            confirmLabel="Cancel invitation"
                                            form={WorkspaceInvitationController.destroy.form(
                                                { invitation: invitation.code },
                                            )}
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </SettingsCardBody>
            </SettingsCard>
        </>
    );
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '';
}
