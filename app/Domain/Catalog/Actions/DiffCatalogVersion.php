<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Data\CatalogFieldDiff;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Task\Models\Task;
use BackedEnum;

/**
 * 3-way diff of a project task against its newer catalog version. Base is
 * `tasks.catalog_snapshot` (catalog values when last copied): a field is
 * listed when the catalog changed it, and is a conflict when the founder
 * changed it too. Without a snapshot every differing field is a conflict.
 */
class DiffCatalogVersion
{
    /**
     * Task field => catalog field.
     *
     * @var array<string, string>
     */
    public const array FIELDS = [
        'title' => 'title',
        'summary' => 'summary',
        'body_md' => 'body_md',
        'priority' => 'priority_default',
        'completion_criteria' => 'completion_criteria',
        'expected_outputs' => 'expected_outputs',
    ];

    /**
     * @var array<string, string>
     */
    private const array LABELS = [
        'title' => 'Title',
        'summary' => 'Summary',
        'body_md' => 'Description',
        'priority' => 'Priority',
        'completion_criteria' => 'Completion criteria',
        'expected_outputs' => 'Expected outputs',
    ];

    /**
     * Catalog values keyed by task field, stored as `tasks.catalog_snapshot`.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(CatalogTask $catalogTask): array
    {
        $snapshot = [];

        foreach (self::FIELDS as $field => $catalogField) {
            $snapshot[$field] = self::plain($catalogTask->getAttribute($catalogField));
        }

        return $snapshot;
    }

    /**
     * Newer published catalog task for the project task, or null when up to date.
     */
    public function newerVersion(Task $task): ?CatalogTask
    {
        $catalogTask = $task->catalogTask;

        if ($catalogTask === null || $catalogTask->status !== CatalogStatus::Published || $catalogTask->version <= (int) $task->catalog_version) {
            return null;
        }

        return $catalogTask;
    }

    /**
     * @return list<CatalogFieldDiff>
     */
    public function handle(Task $task): array
    {
        $catalogTask = $this->newerVersion($task);

        if ($catalogTask === null) {
            return [];
        }

        $base = $task->catalog_snapshot;
        $diffs = [];

        foreach (self::snapshot($catalogTask) as $field => $catalog) {
            $founder = self::plain($task->getAttribute($field));

            if ($founder == $catalog || ($base !== null && ($base[$field] ?? null) == $catalog)) {
                continue;
            }

            $diffs[] = new CatalogFieldDiff(
                field: $field,
                label: self::LABELS[$field],
                founder: $founder,
                catalog: $catalog,
                isConflict: $base === null || ($base[$field] ?? null) != $founder,
            );
        }

        return $diffs;
    }

    private static function plain(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
