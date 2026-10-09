<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Jobs\FlagCatalogUpdates;
use App\Domain\Catalog\Models\CatalogTask;

/**
 * Makes the current version live: new packs apply it and projects holding
 * an older version get `has_catalog_update`.
 */
class PublishCatalogTask
{
    public function handle(CatalogTask $task): CatalogTask
    {
        $task->forceFill([
            'status' => CatalogStatus::Published,
            'published_at' => now(),
        ])->save();

        FlagCatalogUpdates::dispatch($task->id)->afterCommit();

        return $task;
    }
}
