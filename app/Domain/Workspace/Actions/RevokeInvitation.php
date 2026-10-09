<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeInvitation
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * The invitation is deleted, so the workspace is the activity subject.
     */
    public function handle(WorkspaceInvitation $invitation, User $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            $invitation->delete();

            $this->activity->record('workspace.invitation_revoked', $invitation->workspace, [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
            ], Actor::user($actor));
        });
    }
}
