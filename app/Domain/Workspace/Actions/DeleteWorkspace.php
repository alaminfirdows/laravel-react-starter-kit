<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteWorkspace
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * Soft delete: activity rows survive, so the event is recorded. The slug stays reserved. Members currently in the
     * workspace fall back to another workspace on their next request.
     */
    public function handle(Workspace $workspace): void
    {
        if ($workspace->isPersonal()) {
            throw new InvalidArgumentException('Personal workspaces cannot be deleted.');
        }

        DB::transaction(function () use ($workspace): void {
            Workspace::query()->lockForUpdate()->findOrFail($workspace->id);

            $workspace->invitations()->delete();

            User::query()
                ->where('current_workspace_id', $workspace->id)
                ->update(['current_workspace_id' => null]);

            $workspace->delete();

            $this->activity->record('workspace.deleted', $workspace, [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
            ], Actor::current());
        });
    }
}
