<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Jobs\FlagCatalogUpdates;
use App\Domain\Catalog\Models\CatalogTask;
use Illuminate\Support\Facades\DB;

/**
 * Makes the current version live: new packs apply it and projects holding
 * an older version get `has_catalog_update`.
 */
class PublishCatalogTask
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(CatalogTask $task): CatalogTask
    {
        DB::transaction(function () use ($task): void {
            $task->forceFill([
                'status' => CatalogStatus::Published,
                'published_at' => now(),
            ])->save();

            $this->activity->record('catalog.task_published', $task, [
                'key' => $task->key,
                'version' => $task->version,
            ], global: true);

            FlagCatalogUpdates::dispatch($task->id)->afterCommit();
        });

        return $task;
    }
}
