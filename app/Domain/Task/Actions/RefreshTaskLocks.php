<?php

namespace App\Domain\Task\Actions;

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use Illuminate\Support\Facades\DB;

/**
 * DATA_MODEL §F.1: a task is locked while any hard dependency is not done/skipped.
 */
class RefreshTaskLocks
{
    public function handle(Project $project): void
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

        $project->tasks()
            ->where('status', TaskStatus::Todo)
            ->whereIn('id', $blocked)
            ->update(['status' => TaskStatus::Locked, 'updated_at' => now()]);

        $project->tasks()
            ->where('status', TaskStatus::Locked)
            ->whereNotIn('id', $blocked)
            ->update(['status' => TaskStatus::Todo, 'updated_at' => now()]);
    }
}
