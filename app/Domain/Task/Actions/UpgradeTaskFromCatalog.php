<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Actions\DiffCatalogVersion;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;

/**
 * Moves a project task to the newer catalog version. Only the chosen fields
 * take the catalog value; other fields keep the founder version. The task
 * then holds the new version, so the update flag clears.
 */
class UpgradeTaskFromCatalog
{
    public function __construct(
        protected DiffCatalogVersion $diff,
        protected ActivityRecorder $activity,
    ) {}

    /**
     * @param  list<string>  $fields  task fields to take from the catalog
     */
    public function handle(Task $task, array $fields, Actor $actor): Task
    {
        $catalogTask = $this->diff->newerVersion($task);

        if ($catalogTask === null) {
            throw InvalidTaskTransition::noCatalogUpdate();
        }

        $applied = [];

        foreach ($this->diff->handle($task) as $diff) {
            if (in_array($diff->field, $fields, true)) {
                $task->setAttribute($diff->field, $diff->catalog);
                $applied[] = $diff->field;
            }
        }

        if (in_array('body_md', $applied, true)) {
            $task->setAttribute('body_doc', $catalogTask->body_doc);
        }

        $previousVersion = $task->catalog_version;

        $task->forceFill([
            'catalog_version' => $catalogTask->version,
            'catalog_snapshot' => DiffCatalogVersion::snapshot($catalogTask),
            'has_catalog_update' => false,
        ])->save();

        $this->activity->record('task.catalog_upgraded', $task, [
            'before' => ['catalog_version' => $previousVersion],
            'after' => ['catalog_version' => $catalogTask->version],
            'fields' => $applied,
        ], $actor);

        return $task;
    }
}
