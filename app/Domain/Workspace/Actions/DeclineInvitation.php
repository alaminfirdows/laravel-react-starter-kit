<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Exceptions\InvalidInvitationException;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Models\User;

class DeclineInvitation
{
    /**
     * @throws InvalidInvitationException
     */
    public function handle(WorkspaceInvitation $invitation, User $user): void
    {
        if (! $invitation->isFor($user)) {
            throw InvalidInvitationException::emailMismatch();
        }

        if ($invitation->isAccepted()) {
            throw InvalidInvitationException::alreadyAccepted();
        }

        $invitation->delete();
    }
}
