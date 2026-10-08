<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Exceptions\InvalidInvitationException;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    /**
     * @throws InvalidInvitationException
     */
    public function handle(WorkspaceInvitation $invitation, User $user): Workspace
    {
        return DB::transaction(function () use ($invitation, $user): Workspace {
            $invitation = WorkspaceInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if (! $invitation->isFor($user)) {
                throw InvalidInvitationException::emailMismatch();
            }

            if ($invitation->isAccepted()) {
                throw InvalidInvitationException::alreadyAccepted();
            }

            if ($invitation->isExpired()) {
                throw InvalidInvitationException::expired();
            }

            /** @var Workspace $workspace */
            $workspace = $invitation->workspace()->firstOrFail();

            if (! $user->belongsToWorkspace($workspace)) {
                $workspace->memberships()->create([
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                    'invited_by' => $invitation->invited_by,
                    'joined_at' => now(),
                ]);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            $user->switchWorkspace($workspace);

            return $workspace;
        });
    }
}
