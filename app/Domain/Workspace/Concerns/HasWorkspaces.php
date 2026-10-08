<?php

namespace App\Domain\Workspace\Concerns;

use App\Domain\Workspace\Data\UserWorkspace;
use App\Domain\Workspace\Data\WorkspacePermissions;
use App\Domain\Workspace\Enums\WorkspacePermission;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Enums\WorkspaceType;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * @property string|null $current_workspace_id
 * @property-read Workspace|null $currentWorkspace
 */
trait HasWorkspaces
{
    /**
     * @return BelongsToMany<Workspace, $this, WorkspaceMember, 'membership'>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->using(WorkspaceMember::class)
            ->as('membership')
            ->withPivot(['id', 'role', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function personalWorkspace(): ?Workspace
    {
        return $this->ownedWorkspaces()
            ->where('type', WorkspaceType::Personal)
            ->oldest()
            ->first();
    }

    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaceRole($workspace) !== null;
    }

    public function isCurrentWorkspace(Workspace $workspace): bool
    {
        return $this->current_workspace_id === $workspace->id;
    }

    public function ownsWorkspace(Workspace $workspace): bool
    {
        return $workspace->owner_id === $this->id;
    }

    public function workspaceRole(Workspace $workspace): ?WorkspaceRole
    {
        // Eloquent value() applies the enum cast.
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $this->id)
            ->value('role');
    }

    public function hasWorkspacePermission(Workspace $workspace, WorkspacePermission $permission): bool
    {
        return $this->workspaceRole($workspace)?->hasPermission($permission) ?? false;
    }

    /**
     * Make the given workspace the current one. Returns false if the user
     * is not a member.
     */
    public function switchWorkspace(Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($workspace)) {
            return false;
        }

        if (! $this->isCurrentWorkspace($workspace)) {
            $this->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        $this->setRelation('currentWorkspace', $workspace);

        return true;
    }

    /**
     * Workspace to land on when the current one is not available:
     * personal workspace first, then the oldest membership.
     */
    public function fallbackWorkspace(?Workspace $excluding = null): ?Workspace
    {
        return $this->workspaces()
            ->when($excluding, fn ($query) => $query->whereKeyNot($excluding->id))
            ->orderByRaw('case when workspaces.type = ? then 0 else 1 end', [WorkspaceType::Personal->value])
            ->orderBy('workspace_members.created_at')
            ->first();
    }

    /**
     * Current workspace if still accessible, else the fallback (persisted).
     */
    public function resolveCurrentWorkspace(): ?Workspace
    {
        $current = $this->currentWorkspace;

        if ($current && $this->belongsToWorkspace($current)) {
            return $current;
        }

        $fallback = $this->fallbackWorkspace();

        $this->forceFill(['current_workspace_id' => $fallback?->id])->save();
        $this->setRelation('currentWorkspace', $fallback);

        return $fallback;
    }

    public function toUserWorkspace(Workspace $workspace, ?WorkspaceRole $role = null): UserWorkspace
    {
        $role ??= $this->workspaceRole($workspace);

        return new UserWorkspace(
            id: $workspace->id,
            name: $workspace->name,
            slug: $workspace->slug,
            isPersonal: $workspace->isPersonal(),
            status: $workspace->status->value,
            logoUrl: $workspace->logo_url,
            role: $role?->value,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentWorkspace($workspace),
        );
    }

    /**
     * @return Collection<int, UserWorkspace>
     */
    public function toUserWorkspaces(): Collection
    {
        return $this->workspaces()
            ->orderByRaw('case when workspaces.type = ? then 0 else 1 end', [WorkspaceType::Personal->value])
            ->orderBy('workspaces.name')
            ->get()
            ->map(fn (Workspace $workspace): UserWorkspace => $this->toUserWorkspace(
                $workspace,
                $workspace->membership->role,
            ))
            ->values();
    }

    public function toWorkspacePermissions(Workspace $workspace): WorkspacePermissions
    {
        $gate = Gate::forUser($this);

        return new WorkspacePermissions(
            canUpdateWorkspace: $gate->allows('update', $workspace),
            canDeleteWorkspace: $gate->allows('delete', $workspace),
            canTransferOwnership: $gate->allows('transferOwnership', $workspace),
            canLeaveWorkspace: $gate->allows('leave', $workspace),
            canUpdateMember: $gate->allows('updateAnyMember', $workspace),
            canRemoveMember: $gate->allows('removeAnyMember', $workspace),
            canCreateInvitation: $gate->allows('inviteMember', $workspace),
            canCancelInvitation: $gate->allows('cancelInvitation', $workspace),
        );
    }
}
