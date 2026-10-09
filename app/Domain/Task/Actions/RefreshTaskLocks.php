<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * DATA_MODEL §F.1: a task is locked while any hard dependency is not done/skipped.
 * Saves each changed task, so `TaskStatusChanged` broadcasts and activity records the change.
 */
class RefreshTaskLocks
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Project $project, ?Actor $actor = null): void
    {
        $blocked = DB::table('task_dependencies as d')
            ->join('tasks as dep', 'dep.id', '=', 'd.depends_on_id')
            ->join('tasks as t', 't.id', '=', 'd.task_id')
            ->where('t.project_id', $project->id)
            ->where('d.kind', 'hard')
            ->whereNull('dep.deleted_at')
            ->whereNotIn('dep.status', [TaskStatus::Done->value, TaskStatus::Skipped->value])
            ->distinct()
            ->pluck('d.task_id');

        $toLock = $project->tasks()->where('status', TaskStatus::Todo)->whereIn('id', $blocked)->get();
        $toUnlock = $project->tasks()->where('status', TaskStatus::Locked)->whereNotIn('id', $blocked)->get();

        $toLock->each(fn (Task $task) => $this->change($task, TaskStatus::Locked, 'task.locked', $actor));
        $toUnlock->each(fn (Task $task) => $this->change($task, TaskStatus::Todo, 'task.unlocked', $actor));
    }

    private function change(Task $task, TaskStatus $to, string $event, ?Actor $actor): void
    {
        $task->forceFill(['status' => $to])->save();

        $this->activity->record($event, $task, [], $actor);
    }
}
