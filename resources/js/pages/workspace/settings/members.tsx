import { Head, router, usePage } from '@inertiajs/react';
import { LogOut } from '@/components/animated-icons';
import WorkspaceMemberController from '@/actions/App/Domain/Workspace/Http/Controllers/WorkspaceMemberController';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { SettingsCard, SettingsCardBody } from '@/components/settings-card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

            <SettingsCard
                title="Members"
                description={`People with access to ${currentWorkspace?.name ?? 'this workspace'}`}
                className="max-w-full"
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="pl-5">Member</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead className="pr-5 text-right">
                                <span className="sr-only">Actions</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {members.map((member) => (
                            <TableRow
                                key={member.id}
                                data-test={`member-${member.id}`}
                            >
                                <TableCell className="pl-5">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <MemberInfo member={member} />
                                        {member.isCurrentUser && (
                                            <Badge variant="outline">You</Badge>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
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
                                </TableCell>
                                <TableCell className="pr-5">
                                    <div className="flex items-center justify-end gap-1">
                                        {workspacePermissions?.canTransferOwnership &&
                                            !member.isCurrentUser && (
                                                <TransferOwnership
                                                    member={member}
                                                />
                                            )}

                                        {member.canRemove && (
                                            <ConfirmActionDialog
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
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
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </SettingsCard>

            {workspacePermissions?.canLeaveWorkspace && (
                <SettingsCard
                    destructive
                    title="Leave workspace"
                    description="You lose access until someone invites you again"
                >
                    <SettingsCardBody>
                        <div>
                            <ConfirmActionDialog
                                trigger={
                                    <Button variant="outline">
                                        <LogOut data-icon="inline-start" />
                                        Leave workspace
                                    </Button>
                                }
                                title={`Leave ${currentWorkspace?.name}?`}
                                description="You lose access to this workspace and its data."
                                confirmLabel="Leave workspace"
                                form={WorkspaceMemberController.leave.form()}
                            />
                        </div>
                    </SettingsCardBody>
                </SettingsCard>
            )}
        </>
    );
}

function MemberInfo({ member }: { member: WorkspaceMember }) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="size-8 rounded-full">
                <AvatarFallback className="bg-primary/10 font-medium text-primary dark:bg-primary/20">
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
