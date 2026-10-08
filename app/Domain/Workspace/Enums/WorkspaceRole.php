<?php

namespace App\Domain\Workspace\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Higher level means more authority.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Member => 2,
            self::Viewer => 1,
        };
    }

    public function isAtLeast(self $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * Roles with at least the given authority, for `whereIn('role', …)`.
     *
     * @return list<self>
     */
    public static function atLeast(self $role): array
    {
        return array_values(array_filter(self::cases(), fn (self $case): bool => $case->isAtLeast($role)));
    }

    public function outranks(self $role): bool
    {
        return $this->level() > $role->level();
    }

    /**
     * @return list<WorkspacePermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => WorkspacePermission::cases(),
            self::Admin => [
                WorkspacePermission::UpdateWorkspace,
                WorkspacePermission::UpdateMember,
                WorkspacePermission::RemoveMember,
                WorkspacePermission::CreateInvitation,
                WorkspacePermission::CancelInvitation,
            ],
            self::Member, self::Viewer => [],
        };
    }

    public function hasPermission(WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Roles that can be given through invitations or role changes.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Member, self::Viewer];
    }

    /**
     * Roles this role is allowed to give to other members.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return array_values(array_filter(
            self::assignable(),
            fn (self $role): bool => $this->outranks($role),
        ));
    }

    /**
     * @param  list<self>  $roles
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $roles): array
    {
        return array_map(fn (self $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
        ], $roles);
    }
}
