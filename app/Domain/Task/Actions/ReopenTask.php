<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

class ReopenTask
{
    public function __construct(
        protected RollupTaskStatus $rollup,
        protected RefreshTaskLocks $refreshLocks,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Task $task, Actor $actor): Task
    {
        if (! $task->status->isClosed()) {
            return $task;
        }

        if (! $task->isLeaf()) {
            throw InvalidTaskTransition::notLeaf($task);
        }

        return DB::transaction(function () use ($task, $actor): Task {
            $cleared = ['completed_at' => null, 'completed_by_type' => null, 'completed_by_id' => null];

            $reopened = $task->actions()
                ->where('status', ActionStatus::Done)
                ->where('completed_by_type', ActorType::User->value)
                ->get()
                ->each(function (TaskAction $action) use ($cleared): void {
                    $action->forceFill(['status' => ActionStatus::Pending, ...$cleared])->save();
                });

            $from = $task->status;
            $task->forceFill([
                'status' => TaskStatus::Todo,
                'progress_pct' => 0,
                'verification' => Verification::None,
                ...$cleared,
            ])->save();

            $this->activity->record('task.reopened', $task, array_filter([
                'from' => $from->value,
                'reopened_actions' => $reopened->modelKeys(),
            ]), $actor);
            $this->rollup->handle($task, $actor);
            $this->refreshLocks->handle($task->project, $actor);

            return $task;
        });
    }
}
