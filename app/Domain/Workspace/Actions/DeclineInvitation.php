<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Exceptions\InvalidInvitationException;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeclineInvitation
{
    public function __construct(protected ActivityRecorder $activity) {}

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

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->delete();

            $this->activity->record('workspace.invitation_declined', $invitation->workspace, [
                'email' => $invitation->email,
                'role' => $invitation->role->value,
            ], Actor::user($user));
        });
    }
}
