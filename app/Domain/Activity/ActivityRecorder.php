<?php

namespace App\Domain\Activity;

use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * The only write path for activity_log (DATA_MODEL §E).
 */
class ActivityRecorder
{
    /**
     * A null subject or one without `workspace_id` (catalog) is a global event.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(string $event, ?Model $subject, array $properties = [], ?Actor $actor = null): Activity
    {
        $actor ??= Actor::current();

        $workspaceId = $subject instanceof Workspace ? $subject->id : $subject?->getAttribute('workspace_id');
        $attributes = [
            'workspace_id' => $workspaceId,
            'project_id' => $subject instanceof Project ? $subject->id : $subject?->getAttribute('project_id'),
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'client_name' => $actor->clientName,
            'channel' => $actor->channel,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ];

        // Global subjects (catalog) have no workspace: skip the creating hook
        // that would otherwise stamp the admin's current workspace.
        return $workspaceId === null
            ? Activity::withoutEvents(fn (): Activity => Activity::create($attributes))
            : Activity::create($attributes);
    }
}
