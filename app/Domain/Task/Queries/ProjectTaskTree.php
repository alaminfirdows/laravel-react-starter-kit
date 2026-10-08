<?php

namespace App\Domain\Task\Queries;

use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Data\TaskGroupData;
use App\Domain\Task\Data\TaskTreeData;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Collection;

/**
 * Sidebar tree: root tasks grouped by catalog category, subtrees nested in memory.
 */
class ProjectTaskTree
{
    public function handle(Project $project): TaskTreeData
    {
        $tasks = $project->tasks()
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->get(['id', 'project_id', 'parent_id', 'title', 'status', 'progress_pct', 'depth', 'category_key']);

        /** @var Collection<string, Collection<int, Task>> $children */
        $children = $tasks->groupBy(fn (Task $task): string => $task->parent_id ?? 'root');

        $tasks->each(fn (Task $task) => $task->setRelation('children', $children->get($task->id, new Collection)->values()));

        $roots = $children->get('root', new Collection);
        $categories = CatalogCategory::query()
            ->whereIn('key', $roots->pluck('category_key')->filter()->unique())
            ->get()
            ->keyBy('key');

        $groups = $roots
            ->groupBy(fn (Task $task): string => $task->category_key ?? 'other')
            ->sortBy(fn (Collection $group, string $key): int => $categories->get($key)->sort_order ?? PHP_INT_MAX)
            ->map(fn (Collection $group, string $key): TaskGroupData => new TaskGroupData(
                key: $key,
                name: $categories->get($key)->name ?? __('Other'),
                icon: $categories->get($key)?->icon,
                progressPct: $this->meanLeafProgress($group),
                tasks: $group->values(),
            ))
            ->values()
            ->all();

        return new TaskTreeData($this->meanLeafProgress($roots), array_values($groups));
    }

    /**
     * @param  Collection<int, Task>  $roots
     */
    private function meanLeafProgress(Collection $roots): int
    {
        return (int) round($this->leaves($roots)->avg('progress_pct') ?? 0);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    private function leaves(Collection $tasks): Collection
    {
        return $tasks->flatMap(fn (Task $task): Collection => $task->children->isEmpty()
            ? new Collection([$task])
            : $this->leaves($task->children))->values();
    }
}
