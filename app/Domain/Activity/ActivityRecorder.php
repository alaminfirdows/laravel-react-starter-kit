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
     * @param  array<string, mixed>  $properties
     */
    public function record(string $event, Model $subject, array $properties = [], ?Actor $actor = null): Activity
    {
        $actor ??= Actor::current();

        return Activity::create([
            'workspace_id' => $subject instanceof Workspace ? $subject->id : $subject->getAttribute('workspace_id'),
            'project_id' => $subject instanceof Project ? $subject->id : $subject->getAttribute('project_id'),
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'client_name' => $actor->clientName,
            'channel' => $actor->channel,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => $properties ?: null,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
