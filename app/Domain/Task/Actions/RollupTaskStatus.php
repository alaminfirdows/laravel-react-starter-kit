<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Collection;

/**
 * DATA_MODEL §F.3: parent progress and status follow their leaves.
 * Synchronous in P0 (trees are small); becomes a debounced job in P1.
 */
class RollupTaskStatus
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Task $changed, Actor $actor): void
    {
        if ($changed->parent_id === null) {
            return;
        }

        /** @var Collection<string, Task> $tasks */
        $tasks = Task::query()
            ->where('project_id', $changed->project_id)
            ->get(['id', 'parent_id', 'status', 'progress_pct', 'title', 'project_id', 'workspace_id', 'completed_at', 'verification'])
            ->keyBy('id');
        $children = $tasks->groupBy('parent_id');

        for ($id = $changed->parent_id; $id !== null; $id = $tasks[$id]->parent_id) {
            $this->recalculate($tasks[$id], $children, $actor);
        }
    }

    /**
     * @param  Collection<string|int, Collection<int, Task>>  $children
     */
    private function recalculate(Task $task, Collection $children, Actor $actor): void
    {
        $leaves = $this->leaves($task, $children);
        $direct = $children->get($task->id, collect());
        $from = $task->status;

        $progress = (int) round($leaves->avg('progress_pct') ?? 0);
        $to = match (true) {
            $direct->every(fn (Task $c): bool => $c->status->isClosed()) => TaskStatus::Done,
            $leaves->contains(fn (Task $l): bool => $l->progress_pct > 0 || $l->status === TaskStatus::InProgress) => TaskStatus::InProgress,
            $from === TaskStatus::Locked => TaskStatus::Locked,
            default => TaskStatus::Todo,
        };

        $task->forceFill([
            'progress_pct' => $progress,
            'status' => $to,
            'completed_at' => $to === TaskStatus::Done ? ($task->completed_at ?? now()) : null,
            'verification' => $to === TaskStatus::Done ? Verification::SelfReported : Verification::None,
        ]);

        if ($task->isDirty()) {
            $task->save();
        }

        if ($from !== $to) {
            $this->activity->record('task.status_changed', $task, ['from' => $from->value, 'to' => $to->value], $actor);
        }
    }

    /**
     * @param  Collection<string|int, Collection<int, Task>>  $children
     * @return Collection<int, Task>
     */
    private function leaves(Task $task, Collection $children): Collection
    {
        $direct = $children->get($task->id);

        if ($direct === null) {
            return collect([$task]);
        }

        return $direct->flatMap(fn (Task $child): Collection => $this->leaves($child, $children))->values();
    }
}
