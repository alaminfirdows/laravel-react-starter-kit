<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Upserts the YAML catalog by stable keys. Catalog is immutable at runtime
 * (CLAUDE.md rule 7): this is the only writer.
 */
class ImportCatalog
{
    public const int MAX_DEPTH = 2;

    /** @var array{categories:int, tasks:int, actions:int, prompts:int, packs:int} */
    private array $counts;

    /** @var array<string, int> */
    private array $categoryIds = [];

    /** @var array<string, int> */
    private array $promptIds = [];

    /** @var array<string, int> */
    private array $taskIds = [];

    /** @var list<array{file:string, task:string, depends_on:string, kind:string}> */
    private array $pendingDependencies = [];

    /**
     * @return array{categories:int, tasks:int, actions:int, prompts:int, packs:int}
     */
    public function handle(string $directory): array
    {
        $this->counts = ['categories' => 0, 'tasks' => 0, 'actions' => 0, 'prompts' => 0, 'packs' => 0];

        DB::transaction(function () use ($directory): void {
            $this->importCategories($this->read("{$directory}/categories.yaml"));
            $this->importPrompts($this->read("{$directory}/prompts.yaml"));

            foreach (File::glob("{$directory}/tasks/*.yaml") as $file) {
                foreach ($this->read($file)['tasks'] ?? [] as $i => $task) {
                    $this->importTask(basename($file), $task, null, 0, $i);
                }
            }

            $this->importDependencies();
            $this->importPacks($this->read("{$directory}/packs.yaml"));
        });

        return $this->counts;
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        return File::exists($file) ? (array) Yaml::parseFile($file) : [];
    }

    /** @param array<string, mixed> $data */
    private function importCategories(array $data): void
    {
        foreach ($data['categories'] ?? [] as $i => $row) {
            $category = CatalogCategory::updateOrCreate(['key' => $row['key']], [
                'name' => $row['name'],
                'phase' => $row['phase'],
                'icon' => $row['icon'] ?? null,
                'description_md' => $row['description_md'] ?? null,
                'sort_order' => $row['sort_order'] ?? $i,
            ]);
            $this->categoryIds[$category->key] = $category->id;
            $this->counts['categories']++;
        }
    }

    /** @param array<string, mixed> $data */
    private function importPrompts(array $data): void
    {
        foreach ($data['prompts'] ?? [] as $row) {
            $attributes = [
                'title' => $row['title'],
                'launcher_md' => $row['launcher_md'] ?? null,
                'full_md' => $row['full_md'],
                'variables' => $row['variables'] ?? null,
                'target' => $row['target'] ?? 'chat',
                'skill_keys' => $row['skills'] ?? null,
            ];
            $prompt = $this->upsertVersioned(PromptTemplate::query()->firstOrNew(['key' => $row['key']]), $attributes);
            $this->promptIds[$prompt->key] = $prompt->id;
            $this->counts['prompts']++;
        }
    }

    /** @param array<string, mixed> $row */
    private function importTask(string $file, array $row, ?CatalogTask $parent, int $depth, int $position): void
    {
        $key = $row['key'];

        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException("{$file}: task [{$key}] is deeper than 3 levels");
        }

        $categoryId = $parent->category_id ?? $this->categoryIds[$row['category'] ?? ''] ?? null;

        if ($categoryId === null) {
            $category = $row['category'] ?? '';
            throw new InvalidArgumentException("{$file}: task [{$key}] has unknown category [{$category}]");
        }

        $task = $this->upsertVersioned(CatalogTask::query()->firstOrNew(['key' => $key]), [
            'category_id' => $categoryId,
            'parent_id' => $parent?->id,
            'title' => $row['title'],
            'summary' => $row['summary'] ?? null,
            'body_md' => $row['body_md'] ?? null,
            'applicability' => $row['applicability'] ?? null,
            'priority_default' => $row['priority'] ?? 'p2',
            'est_minutes' => $row['est_minutes'] ?? null,
            'difficulty' => $row['difficulty'] ?? null,
            'is_optional' => $row['optional'] ?? false,
            'completion_criteria' => $row['completion_criteria'] ?? null,
            'expected_outputs' => $row['expected_outputs'] ?? null,
            'status' => $row['status'] ?? CatalogStatus::Published->value,
            'sort_order' => $position,
            'actions' => $row['actions'] ?? [],
        ], except: ['actions']);

        $task->published_at ??= now();
        $task->save();

        $this->taskIds[$key] = $task->id;
        $this->counts['tasks']++;

        $this->importActions($file, $task, $row['actions'] ?? []);

        foreach ($row['depends_on'] ?? [] as $dependency) {
            $this->pendingDependencies[] = ['file' => $file, 'task' => $key, 'depends_on' => $dependency['key'], 'kind' => $dependency['kind'] ?? 'hard'];
        }

        foreach ($row['children'] ?? [] as $i => $child) {
            $this->importTask($file, $child, $task, $depth + 1, $i);
        }
    }

    /** @param list<array<string, mixed>> $actions */
    private function importActions(string $file, CatalogTask $task, array $actions): void
    {
        $keys = [];

        foreach ($actions as $i => $row) {
            $promptId = null;

            if (isset($row['prompt'])) {
                $promptId = $this->promptIds[$row['prompt']]
                    ?? throw new InvalidArgumentException("{$file}: action [{$task->key}.{$row['key']}] has unknown prompt [{$row['prompt']}]");
            }

            $task->actions()->updateOrCreate(['key' => $row['key']], [
                'title' => $row['title'],
                'type' => $row['type'],
                'executor' => $row['executor'],
                'instructions_md' => $row['instructions_md'] ?? null,
                'prompt_template_id' => $promptId,
                'config' => $row['config'] ?? null,
                'is_required' => $row['required'] ?? true,
                'requires_approval' => $row['requires_approval'] ?? false,
                'sort_order' => $i,
                'version' => $task->version,
            ]);
            $keys[] = $row['key'];
            $this->counts['actions']++;
        }

        $task->actions()->whereNotIn('key', $keys)->delete();
    }

    private function importDependencies(): void
    {
        foreach ($this->pendingDependencies as $dep) {
            $dependsOn = $this->taskIds[$dep['depends_on']]
                ?? throw new InvalidArgumentException("{$dep['file']}: task [{$dep['task']}] depends on unknown task [{$dep['depends_on']}]");

            DB::table('catalog_task_dependencies')->updateOrInsert(
                ['task_id' => $this->taskIds[$dep['task']], 'depends_on_id' => $dependsOn],
                ['kind' => $dep['kind']],
            );
        }
    }

    /** @param array<string, mixed> $data */
    private function importPacks(array $data): void
    {
        $defaults = [];

        foreach ($data['packs'] ?? [] as $row) {
            if (($row['is_default'] ?? false) && isset($defaults[$row['phase']])) {
                throw new InvalidArgumentException("packs.yaml: phase [{$row['phase']}] has two default packs [{$defaults[$row['phase']]}, {$row['key']}]");
            }

            if ($row['is_default'] ?? false) {
                $defaults[$row['phase']] = $row['key'];
            }

            $pack = $this->upsertVersioned(Pack::query()->firstOrNew(['key' => $row['key']]), [
                'name' => $row['name'],
                'description_md' => $row['description_md'] ?? null,
                'audience' => ['phase' => $row['phase'], ...($row['audience'] ?? [])],
                'is_default' => $row['is_default'] ?? false,
                'status' => $row['status'] ?? CatalogStatus::Published->value,
                'items' => $row['items'],
            ], except: ['items']);

            $pack->items()->delete();

            foreach ($row['items'] as $i => $item) {
                $pack->items()->create([
                    'catalog_task_id' => $this->taskIds[$item['task']]
                        ?? CatalogTask::where('key', $item['task'])->value('id')
                        ?? throw new InvalidArgumentException("packs.yaml: pack [{$row['key']}] has unknown task [{$item['task']}]"),
                    'include_subtree' => $item['include_subtree'] ?? true,
                    'sort_order' => $i,
                ]);
            }

            $this->counts['packs']++;
        }
    }

    /**
     * Save a keyed catalog row; bump version when the authored content hash changes.
     *
     * @template TModel of CatalogTask|PromptTemplate|Pack
     *
     * @param  TModel  $model  existing row or new instance with `key` set
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $except  hashed but not stored
     * @return TModel
     */
    private function upsertVersioned(CatalogTask|PromptTemplate|Pack $model, array $attributes, array $except = []): CatalogTask|PromptTemplate|Pack
    {
        $hash = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));

        if ($model->exists && $model->content_hash === $hash) {
            return $model;
        }

        $model->fill([
            ...array_diff_key($attributes, array_flip($except)),
            'content_hash' => $hash,
            'version' => $model->exists ? $model->version + 1 : 1,
        ])->save();

        return $model;
    }
}
