<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Jobs\FlagCatalogUpdates;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogResource;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Catalog\Models\Skill;
use App\Domain\Catalog\Support\SkillFile;
use App\Domain\Catalog\Support\Versioning;
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

    public function __construct(protected ActivityRecorder $activity) {}

    /** Task YAML keys that are hashed but stored in their own tables. */
    private const array HASH_ONLY = ['actions', 'skills', 'resources'];

    /** @var array{categories:int, skills:int, resources:int, tasks:int, actions:int, prompts:int, packs:int} */
    private array $counts;

    /** @var array<string, int> */
    private array $categoryIds = [];

    /** @var array<string, int> */
    private array $promptIds = [];

    /** @var array<string, int> */
    private array $taskIds = [];

    /** @var array<string, int> */
    private array $skillIds = [];

    /** @var array<string, int> */
    private array $resourceIds = [];

    /** @var list<int> catalog tasks whose version changed in this run */
    private array $changedTaskIds = [];

    /** @var list<array{file:string, task:string, depends_on:string, kind:string}> */
    private array $pendingDependencies = [];

    /**
     * @param  string|null  $skillsDirectory  folders with SKILL.md (`resources/skills`); null skips skill import
     * @return array{categories:int, skills:int, resources:int, tasks:int, actions:int, prompts:int, packs:int}
     */
    public function handle(string $directory, ?string $skillsDirectory = null): array
    {
        $this->counts = ['categories' => 0, 'skills' => 0, 'resources' => 0, 'tasks' => 0, 'actions' => 0, 'prompts' => 0, 'packs' => 0];
        $this->changedTaskIds = [];

        DB::transaction(function () use ($directory, $skillsDirectory): void {
            $this->importCategories($this->read("{$directory}/categories.yaml"));

            if ($skillsDirectory !== null) {
                $this->importSkills($skillsDirectory);
            }

            $this->importResources($this->read("{$directory}/resources.yaml"));
            $this->importPrompts($this->read("{$directory}/prompts.yaml"));

            foreach (File::glob("{$directory}/tasks/*.yaml") as $file) {
                foreach ($this->read($file)['tasks'] ?? [] as $i => $task) {
                    $this->importTask(basename($file), $task, null, 0, $i);
                }
            }

            $this->importDependencies();
            $this->importPacks($this->read("{$directory}/packs.yaml"));

            $this->activity->record('catalog.imported', null, $this->counts);
        });

        foreach ($this->changedTaskIds as $taskId) {
            FlagCatalogUpdates::dispatch($taskId);
        }

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

    private function importSkills(string $directory): void
    {
        foreach (SkillFile::find($directory) as $file) {
            $data = SkillFile::read($file);

            $skill = Skill::updateOrCreate(['key' => $data->name], [
                'title' => $data->title,
                'description' => $data->description,
                'version' => $data->version,
                'source_path' => "resources/skills/{$data->name}",
                'in_plugin' => $data->inPlugin,
                'in_app_agents' => $data->inAppAgents,
                'content_hash' => $data->contentHash,
            ]);
            $this->skillIds[$data->name] = $skill->id;
            $this->counts['skills']++;
        }
    }

    /** @param array<string, mixed> $data */
    private function importResources(array $data): void
    {
        foreach ($data['resources'] ?? [] as $row) {
            $resource = CatalogResource::updateOrCreate(['key' => $row['key']], [
                'type' => $row['type'],
                'title' => $row['title'],
                'url' => $row['url'] ?? null,
                'description_md' => $row['description_md'] ?? null,
                'is_affiliate' => $row['is_affiliate'] ?? false,
                'region' => $row['region'] ?? null,
                'meta' => $row['meta'] ?? null,
            ]);
            $this->resourceIds[$resource->key] = $resource->id;
            $this->counts['resources']++;
        }
    }

    /** @param array<string, mixed> $data */
    private function importPrompts(array $data): void
    {
        foreach ($data['prompts'] ?? [] as $row) {
            $prompt = Versioning::import(PromptTemplate::query()->firstOrNew(['key' => $row['key']]), self::promptAttributes($row));
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

        $task = CatalogTask::query()->firstOrNew(['key' => $key]);
        $previousVersion = $task->exists ? $task->version : null;

        $task = Versioning::import(
            $task,
            self::taskAttributes($row, $categoryId, $parent?->id, $position),
            except: self::HASH_ONLY,
        );

        $task->published_at ??= now();
        $task->save();

        if ($previousVersion !== null && $previousVersion !== $task->version) {
            $this->changedTaskIds[] = $task->id;
        }

        $this->syncSkills($file, $task, $row['skills'] ?? []);
        $this->syncResources($file, $task, $row['resources'] ?? []);

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

    /**
     * Task YAML: `skills: [icp-definition, {key: x, required: false}]`.
     *
     * @param  list<string|array{key:string, required?:bool}>  $skills
     */
    private function syncSkills(string $file, CatalogTask $task, array $skills): void
    {
        $sync = [];

        foreach ($skills as $skill) {
            $skill = is_string($skill) ? ['key' => $skill] : $skill;
            $id = $this->skillIds[$skill['key']]
                ?? Skill::where('key', $skill['key'])->value('id')
                ?? throw new InvalidArgumentException("{$file}: task [{$task->key}] has unknown skill [{$skill['key']}]");
            $sync[$id] = ['required' => $skill['required'] ?? true];
        }

        $task->skills()->sync($sync);
    }

    /**
     * Task YAML: `resources: [stripe-atlas, {key: x, note: '...'}]`.
     *
     * @param  list<string|array{key:string, note?:string}>  $resources
     */
    private function syncResources(string $file, CatalogTask $task, array $resources): void
    {
        $sync = [];

        foreach ($resources as $i => $resource) {
            $resource = is_string($resource) ? ['key' => $resource] : $resource;
            $id = $this->resourceIds[$resource['key']]
                ?? CatalogResource::where('key', $resource['key'])->value('id')
                ?? throw new InvalidArgumentException("{$file}: task [{$task->key}] has unknown resource [{$resource['key']}]");
            $sync[$id] = ['sort_order' => $i, 'note' => $resource['note'] ?? null];
        }

        $task->resources()->sync($sync);
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

            $pack = Versioning::import(Pack::query()->firstOrNew(['key' => $row['key']]), self::packAttributes($row), except: ['items']);

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
     * Hashed prompt attributes for a YAML row (shared with `ExportCatalog`).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function promptAttributes(array $row): array
    {
        return [
            'title' => $row['title'],
            'launcher_md' => $row['launcher_md'] ?? null,
            'full_md' => $row['full_md'],
            'variables' => $row['variables'] ?? null,
            'target' => $row['target'] ?? 'chat',
            'skill_keys' => $row['skills'] ?? null,
        ];
    }

    /**
     * Hashed task attributes for a YAML row (shared with `ExportCatalog`).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function taskAttributes(array $row, int $categoryId, ?int $parentId, int $position): array
    {
        return [
            'category_id' => $categoryId,
            'parent_id' => $parentId,
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
            'skills' => $row['skills'] ?? [],
            'resources' => $row['resources'] ?? [],
        ];
    }

    /**
     * Hashed pack attributes for a YAML row (shared with `ExportCatalog`).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function packAttributes(array $row): array
    {
        return [
            'name' => $row['name'],
            'description_md' => $row['description_md'] ?? null,
            'audience' => ['phase' => $row['phase'], ...($row['audience'] ?? [])],
            'is_default' => $row['is_default'] ?? false,
            'status' => $row['status'] ?? CatalogStatus::Published->value,
            'items' => $row['items'],
        ];
    }
}
