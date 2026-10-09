<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Models\ActionRun;
use Illuminate\Support\Facades\DB;

/**
 * Closes a started run that did not complete its action (failed, or criteria still unmet).
 * A still-running action goes back to `ready`, so the founder can retry.
 */
class FinishRun
{
    public function __construct(
        protected SyncTaskFromActions $sync,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(ActionRun $run, Actor $actor, RunStatus $status, ?string $error = null): ActionRun
    {
        if ($run->status !== RunStatus::Started) {
            return $run;
        }

        return DB::transaction(function () use ($run, $actor, $status, $error): ActionRun {
            $run->forceFill(['status' => $status, 'finished_at' => now(), 'error' => $error])->save();

            $action = $run->action;

            if ($action->status === ActionStatus::Running && $action->last_run_id === $run->id) {
                $action->forceFill(['status' => ActionStatus::Ready])->save();
            }

            $this->activity->record($status === RunStatus::Failed ? 'run.failed' : 'run.finished', $action, ['run_id' => $run->id], $actor);
            $this->sync->handle($action->task, $actor);

            return $run;
        });
    }
}
