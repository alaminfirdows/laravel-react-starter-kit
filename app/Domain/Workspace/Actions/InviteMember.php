<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Notifications\WorkspaceInvitationNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class InviteMember
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * Validation (not a member, no pending invitation, allowed role)
     * happens in the form request.
     */
    public function handle(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role): WorkspaceInvitation
    {
        $invitation = DB::transaction(function () use ($workspace, $inviter, $email, $role): WorkspaceInvitation {
            // Old expired invitations for this email are no longer useful.
            $workspace->invitations()
                ->forEmail($email)
                ->whereNull('accepted_at')
                ->delete();

            $invitation = $workspace->invitations()->create([
                'email' => $email,
                'role' => $role,
                'invited_by' => $inviter->id,
            ]);

            $this->activity->record('workspace.member_invited', $workspace, [
                'email' => $invitation->email,
                'role' => $role->value,
            ], Actor::user($inviter));

            return $invitation;
        });

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation));

        return $invitation;
    }
}
