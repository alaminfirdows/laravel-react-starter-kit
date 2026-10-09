<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Catalog\Data\CatalogActionData;
use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Support\Versioning;
use Illuminate\Support\Facades\DB;

/**
 * Admin create/edit of a task action. An action change is a task content
 * change: the task version bumps and the action carries the new version.
 */
class SaveCatalogAction
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(CatalogTask $task, CatalogActionData $data, ?CatalogAction $action = null): CatalogAction
    {
        $action ??= new CatalogAction([
            'catalog_task_id' => $task->id,
            'sort_order' => $task->actions()->max('sort_order') + 1,
        ]);
        $created = ! $action->exists;
        $action->fill($data->toAttributes());

        if ($action->exists && ! $action->isDirty()) {
            return $action;
        }

        return DB::transaction(function () use ($task, $action, $created): CatalogAction {
            Versioning::edit($task, [], force: true);
            $action->version = $task->version;
            $action->save();

            $this->activity->record($created ? 'catalog.action_created' : 'catalog.action_updated', $action, [
                'catalog_task_id' => $task->id,
                'task_key' => $task->key,
                'task_version' => $task->version,
            ], global: true);

            return $action;
        });
    }
}
