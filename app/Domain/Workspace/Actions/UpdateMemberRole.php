<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateMemberRole
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * Ownership moves only through TransferOwnership.
     */
    public function handle(Workspace $workspace, User $member, WorkspaceRole $role): void
    {
        if ($role === WorkspaceRole::Owner) {
            throw new InvalidArgumentException('Use TransferOwnership to change the owner.');
        }

        DB::transaction(function () use ($workspace, $member, $role): void {
            $membership = $workspace->memberships()
                ->where('user_id', $member->id)
                ->where('role', '!=', WorkspaceRole::Owner->value)
                ->lockForUpdate()
                ->firstOrFail();
            $from = $membership->role;

            $membership->update(['role' => $role]);

            $this->activity->record('workspace.member_role_changed', $workspace, [
                'user_id' => $member->id,
                'from' => $from->value,
                'to' => $role->value,
            ], Actor::current());
        });
    }
}
