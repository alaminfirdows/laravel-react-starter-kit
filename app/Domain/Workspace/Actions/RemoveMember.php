<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveMember
{
    /**
     * Also used for "leave". The owner can never be removed.
     */
    public function handle(Workspace $workspace, User $member): void
    {
        DB::transaction(function () use ($workspace, $member): void {
            $workspace->memberships()
                ->where('user_id', $member->id)
                ->where('role', '!=', WorkspaceRole::Owner->value)
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();

            if ($member->isCurrentWorkspace($workspace)) {
                $member->forceFill(['current_workspace_id' => $member->fallbackWorkspace($workspace)?->id])->save();
            }
        });
    }
}
