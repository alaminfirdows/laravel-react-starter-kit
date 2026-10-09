<?php

namespace App\Domain\Analytics\Queries;

use App\Domain\Analytics\Data\TaskDropOff;
use App\Domain\Task\Enums\TaskStatus;
use Carbon\CarbonInterface;

/**
 * Catalog tasks that founders start but leave: open tasks with no change for
 * `$stalledDays`. Aggregated across workspaces, no row data.
 */
class DropOffByTask
{
    /**
     * @return list<TaskDropOff>
     */
    public function handle(CarbonInterface $since, int $stalledDays = 14, int $limit = 20): array
    {
        $closed = [TaskStatus::Done->value, TaskStatus::Skipped->value];

        return array_values(TaskCompletionStats::taskActivity($since)
            ->selectRaw(
                'catalog_tasks.key, catalog_tasks.title,'
                .' count(distinct tasks.id) as started,'
                .' count(distinct tasks.id) filter (where tasks.status not in (?, ?) and tasks.updated_at < ?) as stalled',
                [...$closed, now()->subDays($stalledDays)],
            )
            ->havingRaw('count(distinct tasks.id) filter (where tasks.status not in (?, ?) and tasks.updated_at < ?) > 0', [...$closed, now()->subDays($stalledDays)])
            ->orderByDesc('stalled')
            ->orderBy('catalog_tasks.key')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): TaskDropOff => new TaskDropOff(
                catalogTaskKey: (string) data_get($row, 'key'),
                title: (string) data_get($row, 'title'),
                started: (int) data_get($row, 'started'),
                stalled: (int) data_get($row, 'stalled'),
            ))
            ->all());
    }
}
