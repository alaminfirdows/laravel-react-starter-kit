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
     * `$global` marks a catalog event: no workspace, subject may be null. Otherwise a subject without `workspace_id` takes the current workspace, and throws when none is set.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(string $event, ?Model $subject, array $properties = [], ?Actor $actor = null, bool $global = false): Activity
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

        // Global events skip the creating hook that would stamp the current workspace.
        return $global
            ? Activity::withoutEvents(fn (): Activity => Activity::create($attributes))
            : Activity::create($attributes);
    }
}
