<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Data\EvidenceData;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\Evidence;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

class AttachEvidence
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(TaskAction $action, EvidenceData $data, Actor $actor): Evidence
    {
        $task = $action->task;

        if ($data->criterionKey !== null && ! collect($task->completion_criteria ?? [])->contains('key', $data->criterionKey)) {
            throw InvalidActionTransition::unknownCriterion($action, $data->criterionKey);
        }

        return DB::transaction(function () use ($action, $task, $data, $actor): Evidence {
            $evidence = Evidence::create([
                'project_id' => $action->project_id,
                'task_id' => $action->task_id,
                'task_action_id' => $action->id,
                'action_run_id' => $action->lastRun?->status === RunStatus::Started ? $action->last_run_id : null,
                'criterion_key' => $data->criterionKey,
                'kind' => $data->kind,
                'label' => $data->label,
                'value' => $data->value,
                'passed' => $data->passed,
                'media_id' => $data->mediaId,
                'created_by_type' => $actor->type,
                'created_by_id' => $actor->id,
            ]);

            if ($task->verification === Verification::None) {
                $task->forceFill(['verification' => Verification::EvidenceAttached])->save();
            }

            $this->activity->record('evidence.attached', $action, [
                'evidence_id' => $evidence->id,
                'kind' => $data->kind->value,
                'criterion_key' => $data->criterionKey,
            ], $actor);

            return $evidence;
        });
    }
}
