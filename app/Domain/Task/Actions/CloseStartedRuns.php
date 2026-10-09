<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\TaskAction;

/**
 * Closes the open runs of an action that is being completed or skipped.
 * Saves each run, so `RunFinished` broadcasts once per run.
 */
class CloseStartedRuns
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(TaskAction $action, RunStatus $status, Actor $actor): void
    {
        $action->runs()
            ->where('status', RunStatus::Started)
            ->get()
            ->each(function (ActionRun $run) use ($action, $status, $actor): void {
                $run->forceFill(['status' => $status, 'finished_at' => now()])->save();

                $this->activity->record('run.finished', $action, ['run_id' => $run->id, 'status' => $status->value], $actor);
            });
    }
}
