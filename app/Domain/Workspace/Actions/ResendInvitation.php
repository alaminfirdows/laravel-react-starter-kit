<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Exceptions\InvalidInvitationException;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ResendInvitation
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * New code (old link stops working) and a fresh expiry window.
     */
    public function handle(WorkspaceInvitation $invitation): WorkspaceInvitation
    {
        if ($invitation->isAccepted()) {
            throw InvalidInvitationException::alreadyAccepted();
        }

        DB::transaction(function () use ($invitation): void {
            $invitation->forceFill([
                'code' => WorkspaceInvitation::generateCode(),
                'expires_at' => now()->addDays(WorkspaceInvitation::EXPIRES_IN_DAYS),
            ])->save();

            $this->activity->record('workspace.invitation_resent', $invitation->workspace, [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
            ], Actor::current());
        });

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation));

        return $invitation;
    }
}
