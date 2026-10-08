<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Exceptions\InvalidInvitationException;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;

class ResendInvitation
{
    /**
     * New code (old link stops working) and a fresh expiry window.
     */
    public function handle(WorkspaceInvitation $invitation): WorkspaceInvitation
    {
        if ($invitation->isAccepted()) {
            throw InvalidInvitationException::alreadyAccepted();
        }

        $invitation->forceFill([
            'code' => WorkspaceInvitation::generateCode(),
            'expires_at' => now()->addDays(WorkspaceInvitation::EXPIRES_IN_DAYS),
        ])->save();

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation));

        return $invitation;
    }
}
