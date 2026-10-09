<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;

/**
 * DATA_MODEL §F.3: a leaf follows its actions — done when every required action is done/skipped.
 */
class SyncTaskFromActions
{
    public function __construct(
        protected RollupTaskStatus $rollup,
        protected RefreshTaskLocks $refreshLocks,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Task $task, Actor $actor): void
    {
        if ($task->status->isClosed() || $task->status === TaskStatus::Locked) {
            return;
        }

        $actions = $task->actions()->get(['id', 'status', 'is_required']);
        $gating = $actions->where('is_required', true)->whenEmpty(fn () => $actions);

        if ($gating->isEmpty()) {
            return;
        }

        $closed = $gating->filter(fn (TaskAction $a): bool => $a->status->isClosed())->count();

        if ($closed === $gating->count()) {
            $this->close($task, $actor);

            return;
        }

        $from = $task->status;
        $to = match (true) {
            $actions->contains('status', ActionStatus::AwaitingApproval) => TaskStatus::AwaitingApproval,
            $actions->contains(fn (TaskAction $a): bool => $a->status !== ActionStatus::Pending) => TaskStatus::InProgress,
            default => $from,
        };

        $task->forceFill([
            'status' => $to,
            'progress_pct' => intdiv($closed * 100, $gating->count()),
            'started_at' => $to === TaskStatus::InProgress ? ($task->started_at ?? now()) : $task->started_at,
        ]);

        if (! $task->isDirty()) {
            return;
        }

        $task->save();

        if ($from !== $to) {
            $this->activity->record('task.status_changed', $task, ['from' => $from->value, 'to' => $to->value], $actor);
        }

        $this->rollup->handle($task, $actor);
    }

    public function close(Task $task, Actor $actor): void
    {
        $from = $task->status;

        $task->forceFill([
            'status' => TaskStatus::Done,
            'progress_pct' => 100,
            'verification' => $task->evidence()->exists() ? Verification::EvidenceAttached : Verification::SelfReported,
            'started_at' => $task->started_at ?? now(),
            'completed_at' => now(),
            'completed_by_type' => $actor->type->value,
            'completed_by_id' => $actor->id,
        ])->save();

        $this->activity->record('task.completed', $task, ['from' => $from->value], $actor);
        $this->rollup->handle($task, $actor);
        $this->refreshLocks->handle($task->project, $actor);
    }
}
