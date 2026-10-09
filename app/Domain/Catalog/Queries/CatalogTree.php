<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogTask;

/**
 * Admin catalog tree: categories with nested tasks (all statuses).
 */
class CatalogTree
{
    /**
     * @return list<array{id: int, name: string, phase: string, tasks: list<array<string, mixed>>}>
     */
    public function handle(): array
    {
        $byParent = [];

        CatalogTask::query()
            ->orderBy('sort_order')
            ->get(['id', 'key', 'title', 'category_id', 'parent_id', 'status', 'version', 'admin_edited_at'])
            ->each(function (CatalogTask $task) use (&$byParent): void {
                $byParent[$task->parent_id ?? 0][] = $task;
            });

        return array_values(CatalogCategory::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CatalogCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'phase' => $category->phase->value,
                'tasks' => $this->nodes($byParent, array_filter(
                    $byParent[0] ?? [],
                    fn (CatalogTask $task): bool => $task->category_id === $category->id,
                )),
            ])
            ->all());
    }

    /**
     * @param  array<int, list<CatalogTask>>  $byParent
     * @param  array<int, CatalogTask>  $tasks
     * @return list<array<string, mixed>>
     */
    private function nodes(array $byParent, array $tasks): array
    {
        return array_values(array_map(fn (CatalogTask $task): array => [
            'key' => $task->key,
            'title' => $task->title,
            'status' => $task->status->value,
            'version' => $task->version,
            'isEdited' => $task->admin_edited_at !== null,
            'children' => $this->nodes($byParent, $byParent[$task->id] ?? []),
        ], $tasks));
    }
}
