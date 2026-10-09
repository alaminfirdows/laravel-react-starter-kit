<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Enums\WorkspaceStatus;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;

class SetWorkspaceStatus
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Workspace $workspace, WorkspaceStatus $status, Actor $actor): Workspace
    {
        if ($workspace->status === $status) {
            return $workspace;
        }

        return DB::transaction(function () use ($workspace, $status, $actor): Workspace {
            $from = $workspace->status;
            $workspace->forceFill(['status' => $status])->save();

            $this->activity->record('workspace.status_changed', $workspace, ['from' => $from->value, 'to' => $status->value], $actor);

            return $workspace;
        });
    }
}
