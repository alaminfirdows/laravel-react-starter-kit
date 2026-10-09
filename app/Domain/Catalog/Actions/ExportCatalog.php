<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogResource;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PackItem;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Catalog\Models\Skill;
use App\Domain\Catalog\Support\Versioning;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes DB catalog content (prompts, tasks, packs) back to YAML so admin
 * edits become the source again. Hashes use the importer's own attribute
 * builders, so a later `catalog:import` of the same files is a no-op.
 * Categories, resources and skills are YAML-only and left untouched.
 */
class ExportCatalog
{
    /** @var array<int, list<CatalogTask>> */
    private array $children = [];

    /**
     * @return array{prompts: int, tasks: int, packs: int}
     */
    public function handle(string $directory): array
    {
        $counts = ['prompts' => 0, 'tasks' => 0, 'packs' => 0];

        DB::transaction(function () use ($directory, &$counts): void {
            $counts['prompts'] = $this->exportPrompts($directory);
            $counts['tasks'] = $this->exportTasks($directory);
            $counts['packs'] = $this->exportPacks($directory);
        });

        return $counts;
    }

    private function exportPrompts(string $directory): int
    {
        $rows = [];

        foreach (PromptTemplate::query()->orderBy('id')->get() as $prompt) {
            $row = $this->compact([
                'key' => $prompt->key,
                'title' => $prompt->title,
                'target' => $prompt->target,
                'skills' => $prompt->skill_keys,
                'variables' => $prompt->variables,
                'launcher_md' => $prompt->launcher_md,
                'full_md' => $prompt->full_md,
            ]);

            $this->synced($prompt, ImportCatalog::promptAttributes($row));
            $rows[] = $row;
        }

        $this->write("{$directory}/prompts.yaml", ['prompts' => $rows]);

        return count($rows);
    }

    private function exportTasks(string $directory): int
    {
        $tasks = CatalogTask::query()
            ->with(['category', 'actions.promptTemplate', 'skills', 'resources', 'dependencies'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $roots = [];

        foreach ($tasks as $task) {
            if ($task->parent_id === null) {
                $roots[] = $task;
            } else {
                $this->children[$task->parent_id][] = $task;
            }
        }

        $existing = array_values(array_filter(File::glob("{$directory}/tasks/*.yaml"), is_string(...)));
        $files = $this->rootFiles($existing);
        $byFile = array_fill_keys(array_map(basename(...), $existing), []);

        foreach ($roots as $root) {
            $file = $files[$root->key] ?? "{$root->category->key}.yaml";
            $byFile[$file][] = $this->taskRow($root, $root->category->key, count($byFile[$file] ?? []));
        }

        foreach ($byFile as $file => $rows) {
            $this->write("{$directory}/tasks/{$file}", ['tasks' => $rows]);
        }

        return $tasks->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRow(CatalogTask $task, ?string $categoryKey, int $position): array
    {
        $row = $this->compact([
            'key' => $task->key,
            'category' => $categoryKey,
            'title' => $task->title,
            'status' => $task->status === CatalogStatus::Published ? null : $task->status->value,
            'summary' => $task->summary,
            'priority' => $task->priority_default->value,
            'est_minutes' => $task->est_minutes,
            'difficulty' => $task->difficulty,
            'optional' => $task->is_optional ?: null,
            'applicability' => $task->applicability,
            'body_md' => $task->body_md,
            'completion_criteria' => $task->completion_criteria,
            'expected_outputs' => $task->expected_outputs,
            'skills' => $this->skills($task->skills),
            'resources' => $this->resources($task->resources),
            'depends_on' => array_values($task->dependencies
                ->map(fn (CatalogTask $dependency): array => ['key' => $dependency->key, 'kind' => (string) $dependency->pivot?->getAttribute('kind')])
                ->all()),
            'actions' => array_values($task->actions->map($this->actionRow(...))->all()),
        ]);

        $this->synced($task, ImportCatalog::taskAttributes($row, $task->category_id, $task->parent_id, $position), ['sort_order' => $position]);

        $children = [];

        foreach ($this->children[$task->id] ?? [] as $child) {
            $children[] = $this->taskRow($child, null, count($children));
        }

        return $this->compact([...$row, 'children' => $children]);
    }

    /**
     * @return array<string, mixed>
     */
    private function actionRow(CatalogAction $action): array
    {
        return $this->compact([
            'key' => $action->key,
            'title' => $action->title,
            'type' => $action->type->value,
            'executor' => $action->executor->value,
            'prompt' => $action->promptTemplate?->key,
            'instructions_md' => $action->instructions_md,
            'config' => $action->config,
            'required' => $action->is_required ? null : false,
            'requires_approval' => $action->requires_approval ?: null,
        ]);
    }

    /**
     * @param  Collection<int, Skill>  $skills
     * @return list<string|array{key: string, required: false}>
     */
    private function skills(Collection $skills): array
    {
        return array_values($skills->map(fn (Skill $skill): string|array => (bool) $skill->pivot?->getAttribute('required')
            ? $skill->key
            : ['key' => $skill->key, 'required' => false])->all());
    }

    /**
     * @param  Collection<int, CatalogResource>  $resources
     * @return list<string|array{key: string, note: string}>
     */
    private function resources(Collection $resources): array
    {
        return array_values($resources->map(function (CatalogResource $resource): string|array {
            $note = $resource->pivot?->getAttribute('note');

            return is_string($note) && $note !== '' ? ['key' => $resource->key, 'note' => $note] : $resource->key;
        })->all());
    }

    private function exportPacks(string $directory): int
    {
        $rows = [];

        foreach (Pack::query()->with('items.catalogTask')->orderBy('id')->get() as $pack) {
            $audience = $pack->audience ?? [];
            $phase = $audience['phase'] ?? null;
            unset($audience['phase']);

            $row = $this->compact([
                'key' => $pack->key,
                'name' => $pack->name,
                'phase' => $phase,
                'audience' => $audience,
                'is_default' => $pack->is_default ?: null,
                'status' => $pack->status === CatalogStatus::Published ? null : $pack->status->value,
                'description_md' => $pack->description_md,
                'items' => array_values($pack->items->map(fn (PackItem $item): array => $this->compact([
                    'task' => $item->catalogTask->key,
                    'include_subtree' => $item->include_subtree ? null : false,
                ]))->all()),
            ]);

            $this->synced($pack, ImportCatalog::packAttributes($row));
            $rows[] = $row;
        }

        $this->write("{$directory}/packs.yaml", ['packs' => $rows]);

        return count($rows);
    }

    /**
     * Root task key => existing tasks/*.yaml file, so exported roots stay in their file.
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private function rootFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            foreach ((array) (Yaml::parseFile($path)['tasks'] ?? []) as $row) {
                if (is_array($row) && is_string($row['key'] ?? null)) {
                    $files[$row['key']] = basename($path);
                }
            }
        }

        return $files;
    }

    /**
     * Store the import hash of the exported row and clear the admin edit mark, without a version bump.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extra
     */
    private function synced(CatalogTask|PromptTemplate|Pack $model, array $attributes, array $extra = []): void
    {
        $model->forceFill([
            ...$extra,
            'content_hash' => Versioning::hash($attributes),
            'admin_edited_at' => null,
        ])->save();
    }

    /**
     * Drop null and empty values so YAML stays minimal.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function compact(array $row): array
    {
        return array_filter($row, fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, Yaml::dump($data, 12, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }
}
