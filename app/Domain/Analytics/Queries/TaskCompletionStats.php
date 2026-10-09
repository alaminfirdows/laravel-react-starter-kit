<?php

namespace App\Domain\Analytics\Queries;

use App\Domain\Activity\Models\Activity;
use App\Domain\Analytics\Data\TaskCompletionStat;
use App\Domain\Task\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

/**
 * Cross-workspace completion numbers per catalog task, from activity_log.
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
                .' count(distinct tasks.id) filter (where activity_log.event = ?) as completed,'
                .' avg(extract(epoch from tasks.completed_at - tasks.created_at) / 3600) filter (where activity_log.event = ?) as avg_hours',
                ['task.completed', 'task.completed'],
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
     */
    public static function taskActivity(CarbonInterface $since): Builder
    {
        return Activity::withoutWorkspaceScope()->toBase()
            ->join('tasks', 'tasks.id', '=', 'activity_log.subject_id')
            ->join('catalog_tasks', 'catalog_tasks.id', '=', 'tasks.catalog_task_id')
            ->where('activity_log.subject_type', (new Task)->getMorphClass())
            ->whereIn('activity_log.event', self::PROGRESS_EVENTS)
            ->where('activity_log.created_at', '>=', $since)
            ->groupBy('catalog_tasks.id', 'catalog_tasks.key', 'catalog_tasks.title');
    }
}
