<?php

namespace App\Domain\Catalog\Jobs;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Task\Models\Task;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Syncs `tasks.has_catalog_update` for project tasks made from one catalog task:
 * published → flag tasks holding an older version; removed or archived → the
 * project task becomes custom (no catalog link) and loses the flag.
 */
class FlagCatalogUpdates implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public function __construct(public int $catalogTaskId) {}

    public function uniqueId(): string
    {
        return (string) $this->catalogTaskId;
    }

    public function handle(): void
    {
        $catalogTask = CatalogTask::query()->find($this->catalogTaskId);
        $linked = Task::withoutWorkspaceScope()->where('catalog_task_id', $this->catalogTaskId);

        if ($catalogTask === null || $catalogTask->status === CatalogStatus::Archived) {
            $linked->update(['catalog_task_id' => null, 'has_catalog_update' => false]);
        } elseif ($catalogTask->status === CatalogStatus::Published) {
            (clone $linked)->where('catalog_version', '<', $catalogTask->version)->update(['has_catalog_update' => true]);
            (clone $linked)->where('catalog_version', '>=', $catalogTask->version)->update(['has_catalog_update' => false]);
        }

        Task::withoutWorkspaceScope()
            ->whereNull('catalog_task_id')
            ->where('has_catalog_update', true)
            ->update(['has_catalog_update' => false]);
    }
}
