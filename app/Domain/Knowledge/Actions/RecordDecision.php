<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Knowledge\Data\DecisionData;
use App\Domain\Knowledge\Models\Decision;
use App\Domain\Project\Jobs\RebuildContextSnapshotJob;
use App\Domain\Project\Models\Project;

class RecordDecision
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Project $project, DecisionData $data, Actor $actor): Decision
    {
        $decision = $project->decisions()->create([
            'workspace_id' => $project->workspace_id,
            'task_id' => $data->taskId,
            'title' => $data->title,
            'decision_md' => $data->decisionMd,
            'rationale_md' => $data->rationaleMd,
            'alternatives' => $data->alternatives ?: null,
            'owner_id' => $actor->type === ActorType::System ? null : $actor->id,
            'decided_on' => ($data->decidedOn ?? now())->toDateString(),
            'revisit_on' => $data->revisitOn?->toDateString(),
            'source' => $data->source,
        ]);

        $this->activity->record('decision.recorded', $decision, ['decision_id' => $decision->id], $actor);
        RebuildContextSnapshotJob::debounce($project->id);

        return $decision;
    }
}
