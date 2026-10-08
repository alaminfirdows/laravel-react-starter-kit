import { Head, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import WorkspaceMemberController from '@/actions/App/Domain/Workspace/Http/Controllers/WorkspaceMemberController';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { RoleOption, WorkspaceMember } from '@/types';

export default function Members({
    members,
    assignableRoles,
}: {
    members: WorkspaceMember[];
    assignableRoles: RoleOption[];
}) {
    const { currentWorkspace, workspacePermissions } = usePage().props;

    return (
        <>
            <Head title="Members" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Members"
                    description={`People with access to ${currentWorkspace?.name ?? 'this workspace'}`}
                />

                <ul className="divide-y rounded-lg border">
                    {members.map((member) => (
                        <li
                            key={member.id}
                            className="flex flex-wrap items-center gap-3 p-4"
                            data-test={`member-${member.id}`}
                        >
                            <div className="flex min-w-0 flex-1 items-center gap-2">
                                <MemberInfo member={member} />
                                {member.isCurrentUser && (
                                    <Badge variant="outline">You</Badge>
                                )}
                            </div>

                            {member.canUpdate ? (
                                <RoleSelect
                                    member={member}
                                    roles={assignableRoles}
                                />
                            ) : (
                                <Badge variant="secondary">
                                    {member.roleLabel}
                                </Badge>
                            )}

                            {workspacePermissions?.canTransferOwnership &&
                                !member.isCurrentUser && (
                                    <TransferOwnership member={member} />
                                )}

                            {member.canRemove && (
                                <ConfirmActionDialog
                                    trigger={
                                        <Button variant="ghost" size="sm">
                                            Remove
                                        </Button>
                                    }
                                    title={`Remove ${member.name}?`}
                                    description="They lose access to this workspace at once."
                                    confirmLabel="Remove member"
                                    form={WorkspaceMemberController.destroy.form(
                                        { member: member.id },
                                    )}
                                />
                            )}
                        </li>
                    ))}
                </ul>
            </div>

            {workspacePermissions?.canLeaveWorkspace && (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Leave workspace"
                        description="You lose access until someone invites you again"
                    />
                    <ConfirmActionDialog
                        trigger={
                            <Button variant="outline">
                                <LogOut />
                                Leave workspace
                            </Button>
                        }
                        title={`Leave ${currentWorkspace?.name}?`}
                        description="You lose access to this workspace and its data."
                        confirmLabel="Leave workspace"
                        form={WorkspaceMemberController.leave.form()}
                    />
                </div>
            )}
        </>
    );
}

function MemberInfo({ member }: { member: WorkspaceMember }) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="size-8 rounded-full">
                <AvatarFallback className="bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(member.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid min-w-0 text-sm leading-tight">
                <span className="truncate font-medium">{member.name}</span>
                <span className="truncate text-xs text-muted-foreground">
                    {member.email}
                </span>
            </div>
        </>
    );
}

function RoleSelect({
    member,
    roles,
}: {
    member: WorkspaceMember;
    roles: RoleOption[];
}) {
    return (
        <Select
            value={member.role}
            onValueChange={(role) =>
                router.visit(
                    WorkspaceMemberController.update({ member: member.id }),
                    { data: { role }, preserveScroll: true },
                )
            }
        >
            <SelectTrigger className="w-32" aria-label="Role">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                        {role.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function TransferOwnership({ member }: { member: WorkspaceMember }) {
    return (
        <ConfirmActionDialog
            trigger={
                <Button variant="ghost" size="sm">
                    Make owner
                </Button>
            }
            title={`Transfer ownership to ${member.name}?`}
            description="You become an admin. Only the new owner can delete the workspace or transfer it again."
            confirmLabel="Transfer ownership"
            form={WorkspaceMemberController.transferOwnership.form({
                member: member.id,
            })}
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor={`password-${member.id}`}>
                        Your password
                    </Label>
                    <PasswordInput
                        id={`password-${member.id}`}
                        name="password"
                        autoComplete="current-password"
                        required
                    />
                    <InputError message={errors.password} />
                </div>
            )}
        </ConfirmActionDialog>
    );
}
