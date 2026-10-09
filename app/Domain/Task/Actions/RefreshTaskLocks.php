<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * DATA_MODEL §F.1: a task is locked while any hard dependency is not done/skipped.
 * Saves each changed task, so `TaskStatusChanged` broadcasts and activity records the change.
 * Rows are locked in id order and re-checked, so a concurrent status change is never overwritten.
 */
class RefreshTaskLocks
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * @param  list<string>|null  $onlyTaskIds  limit to these tasks (null = whole project)
     */
    public function handle(Project $project, ?Actor $actor = null, ?array $onlyTaskIds = null): void
    {
        if ($onlyTaskIds === []) {
            return;
        }

        DB::transaction(function () use ($project, $actor, $onlyTaskIds): void {
            $blocked = DB::table('task_dependencies as d')
                ->join('tasks as dep', 'dep.id', '=', 'd.depends_on_id')
                ->join('tasks as t', 't.id', '=', 'd.task_id')
                ->where('t.project_id', $project->id)
                ->where('d.kind', 'hard')
                ->whereNull('dep.deleted_at')
                ->whereNotIn('dep.status', [TaskStatus::Done->value, TaskStatus::Skipped->value])
                ->distinct()
                ->pluck('d.task_id')
                ->all();

            $project->tasks()
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $toLock) => $toLock->where('status', TaskStatus::Todo)->whereIn('id', $blocked))
                    ->orWhere(fn (Builder $toUnlock) => $toUnlock->where('status', TaskStatus::Locked)->whereNotIn('id', $blocked)))
                ->when($onlyTaskIds !== null, fn (Builder $query) => $query->whereIn('id', $onlyTaskIds))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (Task $task) use ($blocked, $actor): void {
                    $isBlocked = in_array($task->id, $blocked, true);

                    match (true) {
                        $task->status === TaskStatus::Todo && $isBlocked => $this->change($task, TaskStatus::Locked, 'task.locked', $actor),
                        $task->status === TaskStatus::Locked && ! $isBlocked => $this->change($task, TaskStatus::Todo, 'task.unlocked', $actor),
                        default => null,
                    };
                });
        });
    }

    private function change(Task $task, TaskStatus $to, string $event, ?Actor $actor): void
    {
        $task->forceFill(['status' => $to])->save();

        $this->activity->record($event, $task, [], $actor);
    }
}
