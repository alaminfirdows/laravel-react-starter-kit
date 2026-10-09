<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferOwnership
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * New owner must already be a member. The old owner becomes Admin.
     */
    public function handle(Workspace $workspace, User $newOwner): void
    {
        if ($workspace->isPersonal()) {
            throw new InvalidArgumentException('Personal workspaces cannot change owner.');
        }

        DB::transaction(function () use ($workspace, $newOwner): void {
            $locked = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);

            if ($locked->owner_id === $newOwner->id) {
                throw new InvalidArgumentException('User already owns this workspace.');
            }

            $newMembership = $locked->memberships()
                ->where('user_id', $newOwner->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->memberships()
                ->where('role', WorkspaceRole::Owner->value)
                ->update(['role' => WorkspaceRole::Admin->value]);

            $newMembership->update(['role' => WorkspaceRole::Owner]);

            $previousOwnerId = $locked->owner_id;
            $locked->forceFill(['owner_id' => $newOwner->id])->save();

            $this->activity->record('workspace.ownership_transferred', $locked, [
                'from_user_id' => $previousOwnerId,
                'to_user_id' => $newOwner->id,
            ], Actor::current());

            $workspace->owner_id = $newOwner->id;
            $workspace->syncOriginalAttribute('owner_id');
        });
    }
}
