<?php

namespace App\Domain\Analytics\Queries;

use App\Domain\Activity\Models\Activity;
use App\Domain\Analytics\Data\TaskCompletionStat;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Cross-workspace completion numbers per catalog task: started from activity_log progress events,
 * completed from the task row (done, completed in the window), so rollup-closed parents count too.
 * Returns aggregates and catalog titles only, never workspace or project rows.
 */
class TaskCompletionStats
{
    /**
     * Events that mean a founder worked on the task.
     */
    public const array PROGRESS_EVENTS = ['task.status_changed', 'action.started', 'task.completed'];

    /**
     * @return list<TaskCompletionStat>
     */
    public function handle(CarbonInterface $since, int $limit = 20): array
    {
        return array_values(self::taskActivity($since)
            ->selectRaw(
                'catalog_tasks.key, catalog_tasks.title,'
                .' count(distinct tasks.project_id) as projects,'
                .' count(distinct tasks.id) as started,'
                .' count(distinct tasks.id) filter (where tasks.status = ? and tasks.completed_at >= ?) as completed,'
                .' avg(extract(epoch from tasks.completed_at - tasks.created_at) / 3600) filter (where tasks.status = ? and tasks.completed_at >= ?) as avg_hours',
                [TaskStatus::Done->value, $since, TaskStatus::Done->value, $since],
            )
            ->orderByDesc('started')
            ->orderBy('catalog_tasks.key')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): TaskCompletionStat => new TaskCompletionStat(
                catalogTaskKey: (string) data_get($row, 'key'),
                title: (string) data_get($row, 'title'),
                projects: (int) data_get($row, 'projects'),
                started: (int) data_get($row, 'started'),
                completed: (int) data_get($row, 'completed'),
                avgHoursToComplete: data_get($row, 'avg_hours') === null ? null : round((float) data_get($row, 'avg_hours'), 1),
            ))
            ->all());
    }

    /**
     * Progress events on catalog-based tasks, grouped per catalog task.
     * Action events (`action.started`) count for the action's task.
     */
    public static function taskActivity(CarbonInterface $since): Builder
    {
        $actionMorph = (new TaskAction)->getMorphClass();

        return Activity::withoutWorkspaceScope()->toBase()
            ->leftJoin('task_actions', fn (JoinClause $join) => $join
                ->on('task_actions.id', '=', 'activity_log.subject_id')
                ->where('activity_log.subject_type', '=', $actionMorph))
            ->join('tasks', 'tasks.id', '=', DB::raw('coalesce(task_actions.task_id, activity_log.subject_id)'))
            ->join('catalog_tasks', 'catalog_tasks.id', '=', 'tasks.catalog_task_id')
            ->whereIn('activity_log.subject_type', [(new Task)->getMorphClass(), $actionMorph])
            ->whereIn('activity_log.event', self::PROGRESS_EVENTS)
            ->where('activity_log.created_at', '>=', $since)
            ->groupBy('catalog_tasks.id', 'catalog_tasks.key', 'catalog_tasks.title');
    }
}
