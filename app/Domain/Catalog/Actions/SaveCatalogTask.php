<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Data\CatalogTaskData;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Support\Versioning;
use InvalidArgumentException;

/**
 * Admin create/edit of a catalog task. New tasks start as drafts; content
 * changes bump `version`. Projects see the change after PublishCatalogTask.
 */
class SaveCatalogTask
{
    public function handle(CatalogTaskData $data, ?CatalogTask $task = null): CatalogTask
    {
        $task ??= new CatalogTask;
        $attributes = $data->authored();

        if (! $task->exists) {
            $parent = $data->parentId !== null ? CatalogTask::query()->findOrFail($data->parentId) : null;

            if ($parent !== null && $this->depth($parent) >= ImportCatalog::MAX_DEPTH) {
                throw new InvalidArgumentException("Task [{$data->key}] would be deeper than 3 levels.");
            }

            $task->fill([
                'key' => $data->key,
                'parent_id' => $parent?->id,
                'category_id' => $parent->category_id ?? $data->categoryId,
                'status' => CatalogStatus::Draft,
                'sort_order' => CatalogTask::query()->where('parent_id', $parent?->id)->max('sort_order') + 1,
            ]);
        } elseif ($task->parent_id === null) {
            $attributes['category_id'] = $data->categoryId;
        }

        Versioning::edit($task, $attributes);

        return $task;
    }

    private function depth(CatalogTask $task): int
    {
        $depth = 0;

        while ($task->parent_id !== null) {
            $task = CatalogTask::query()->findOrFail($task->parent_id);
            $depth++;
        }

        return $depth;
    }
}
