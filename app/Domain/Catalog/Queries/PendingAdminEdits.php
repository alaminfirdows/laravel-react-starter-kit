<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;

/**
 * Catalog rows edited in the admin UI and not yet exported to YAML.
 */
class PendingAdminEdits
{
    /**
     * @return list<string> labels like `task:planning.icp`
     */
    public function handle(): array
    {
        $labels = [];

        foreach (['task' => CatalogTask::class, 'prompt' => PromptTemplate::class, 'pack' => Pack::class] as $type => $model) {
            foreach ($model::query()->whereNotNull('admin_edited_at')->orderBy('key')->pluck('key') as $key) {
                $labels[] = "{$type}:{$key}";
            }
        }

        return $labels;
    }
}
