<?php

namespace App\Domain\Project\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Catalog\Actions\DiffCatalogVersion;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RefreshTaskLocks;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots catalog rows into project tasks (CLAUDE.md rule 7). Idempotent.
 */
class ApplyPack
{
    public function __construct(
        protected ActivityRecorder $activity,
        protected RefreshTaskLocks $refreshLocks,
    ) {}

    public function handle(Project $project, Pack $pack, ?Actor $actor = null): int
    {
        $actor ??= Actor::current();

        return DB::transaction(function () use ($project, $pack, $actor): int {
            /** @var array<int, string> $map catalog_task_id => task id */
            $map = $project->tasks()->whereNotNull('catalog_task_id')->pluck('id', 'catalog_task_id')->all();
            $existingIds = array_values($map);
            $catalogTasks = $this->catalogTasksFor($pack);
            $blocked = array_flip($this->blockedCatalogTaskIds($project, $map, array_values($catalogTasks->map(fn (array $row): int => $row[0]->id)->all())));
            $created = 0;

            foreach ($catalogTasks as [$catalogTask, $position]) {
                if (isset($map[$catalogTask->id])) {
                    continue;
                }

                $parentId = $map[$catalogTask->parent_id ?? 0] ?? null;
                $parentDepth = $parentId ? Task::query()->whereKey($parentId)->value('depth') : null;

                $task = new Task;
                $task->forceFill([
                    'project_id' => $project->id,
                    'workspace_id' => $project->workspace_id,
                    'catalog_task_id' => $catalogTask->id,
                    'catalog_version' => $catalogTask->version,
                    'catalog_snapshot' => DiffCatalogVersion::snapshot($catalogTask),
                    'parent_id' => $parentId,
                    'depth' => $parentDepth === null ? 0 : $parentDepth + 1,
                    'sort_order' => $position,
                    'category_key' => $catalogTask->category->key,
                    'title' => $catalogTask->title,
                    'summary' => $catalogTask->summary,
                    'body_md' => $catalogTask->body_md,
                    'body_doc' => $catalogTask->body_doc,
                    'status' => isset($blocked[$catalogTask->id]) ? TaskStatus::Locked : TaskStatus::Todo,
                    'priority' => $catalogTask->priority_default,
                    'completion_criteria' => $catalogTask->completion_criteria,
                    'expected_outputs' => $catalogTask->expected_outputs,
                    'verification' => Verification::None,
                ])->save();

                foreach ($catalogTask->actions as $catalogAction) {
                    (new TaskAction)->forceFill([
                        'task_id' => $task->id,
                        'project_id' => $project->id,
                        'catalog_action_id' => $catalogAction->id,
                        'type' => $catalogAction->type,
                        'executor' => $catalogAction->executor,
                        'title' => $catalogAction->title,
                        'instructions_md' => $catalogAction->instructions_md,
                        'prompt_template_id' => $catalogAction->prompt_template_id,
                        'config' => $catalogAction->config,
                        'is_required' => $catalogAction->is_required,
                        'requires_approval' => $catalogAction->requires_approval,
                        'status' => ActionStatus::Pending,
                        'sort_order' => $catalogAction->sort_order,
                    ])->save();
                }

                $map[$catalogTask->id] = $task->id;
                $created++;
            }

            $this->copyDependencies($map);

            $project->packs()->updateOrCreate(['pack_id' => $pack->id], [
                'pack_version' => $pack->version,
                'applied_by' => $actor->type === ActorType::User ? $actor->id : null,
                'applied_at' => now(),
            ]);

            $this->refreshLocks->handle($project, $actor, onlyTaskIds: $existingIds);

            $this->activity->record('project.pack_applied', $project, [
                'pack' => $pack->key,
                'version' => $pack->version,
                'tasks_created' => $created,
            ], $actor);

            return $created;
        });
    }

    /**
     * Pack items + subtrees, parents before children, with sibling position.
     *
     * @return Collection<int, array{0: CatalogTask, 1: int}>
     */
    private function catalogTasksFor(Pack $pack): Collection
    {
        $all = CatalogTask::query()
            ->where('status', CatalogStatus::Published)
            ->with(['category', 'actions'])
            ->orderBy('sort_order')
            ->get();
        $byParent = $all->groupBy(fn (CatalogTask $t) => $t->parent_id ?? 0);
        $result = collect();

        $walk = function (CatalogTask $task, int $position, bool $subtree) use (&$walk, $byParent, $result): void {
            $result->push([$task, $position]);

            if ($subtree) {
                foreach ($byParent->get($task->id, collect())->values() as $i => $child) {
                    $walk($child, $i, true);
                }
            }
        };

        foreach ($pack->items()->get() as $i => $item) {
            $task = $all->firstWhere('id', $item->catalog_task_id);

            if ($task !== null) {
                $walk($task, $i, $item->include_subtree);
            }
        }

        return $result;
    }

    /**
     * New tasks start locked when a hard dependency is open (DATA_MODEL §F.1), so creating
     * them needs no per-task lock event; `project.pack_applied` covers the change.
     *
     * @param  array<int, string>  $map  existing catalog_task_id => task id
     * @param  list<int>  $packCatalogIds
     * @return list<int> catalog task ids that start locked
     */
    private function blockedCatalogTaskIds(Project $project, array $map, array $packCatalogIds): array
    {
        $inProject = array_unique([...array_keys($map), ...$packCatalogIds]);
        $closedExisting = $project->tasks()
            ->whereNotNull('catalog_task_id')
            ->whereIn('status', [TaskStatus::Done, TaskStatus::Skipped])
            ->pluck('catalog_task_id')
            ->flip();

        return array_values(DB::table('catalog_task_dependencies')
            ->whereIn('task_id', array_diff($packCatalogIds, array_keys($map)))
            ->whereIn('depends_on_id', $inProject)
            ->where('kind', 'hard')
            ->get(['task_id', 'depends_on_id'])
            ->reject(fn (object $row): bool => $closedExisting->has($row->depends_on_id))
            ->map(fn (object $row): int => (int) $row->task_id)
            ->unique()
            ->all());
    }

    /**
     * @param  array<int, string>  $map
     */
    private function copyDependencies(array $map): void
    {
        $rows = DB::table('catalog_task_dependencies')
            ->whereIn('task_id', array_keys($map))
            ->whereIn('depends_on_id', array_keys($map))
            ->get()
            ->map(fn (object $row): array => [
                'task_id' => $map[$row->task_id],
                'depends_on_id' => $map[$row->depends_on_id],
                'kind' => $row->kind,
            ])
            ->all();

        DB::table('task_dependencies')->insertOrIgnore($rows);
    }
}
