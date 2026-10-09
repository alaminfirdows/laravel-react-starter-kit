<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;

/**
 * Root catalog tasks a pack can include, for the pack form pickers.
 */
class PackFormOptions
{
    /**
     * @return array<int, array{key: string, title: string, category: string, status: string}>
     */
    public function rootTasks(bool $publishedOnly = false): array
    {
        return CatalogTask::query()
            ->whereNull('parent_id')
            ->when($publishedOnly, fn ($query) => $query->where('status', CatalogStatus::Published))
            ->with('category:id,name')
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->get(['id', 'key', 'title', 'category_id', 'status'])
            ->map(fn (CatalogTask $task): array => [
                'key' => $task->key,
                'title' => $task->title,
                'category' => $task->category->name,
                'status' => $task->status->value,
            ])
            ->values()
            ->all();
    }
}
