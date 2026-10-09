<?php

use App\Domain\Catalog\Actions\ImportCatalog;
use App\Domain\Catalog\Actions\SaveCatalogTask;
use App\Domain\Catalog\Data\CatalogTaskData;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Task\Enums\TaskPriority;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

function syncFixture(): string
{
    $dir = storage_path('framework/testing/catalog-sync-'.uniqid());
    File::copyDirectory(base_path('tests/Fixtures/catalog'), $dir);
    app(ImportCatalog::class)->handle($dir, base_path('tests/Fixtures/skills'));

    return $dir;
}

function editRootBody(string $body): CatalogTask
{
    $task = CatalogTask::query()->where('key', 'plan.interviews')->firstOrFail();

    return app(SaveCatalogTask::class)->handle(new CatalogTaskData(
        key: $task->key,
        categoryId: $task->category_id,
        title: $task->title,
        bodyMd: $body,
        priority: TaskPriority::P1,
    ), $task);
}

test('import refuses while admin edits are not exported, check reports them', function () {
    $dir = syncFixture();
    editRootBody('Admin body');

    $this->artisan('catalog:import', ['path' => $dir, '--skills' => base_path('tests/Fixtures/skills')])
        ->expectsOutputToContain('task:plan.interviews')
        ->assertFailed();

    $this->artisan('catalog:import', ['path' => $dir, '--check' => true])->assertFailed();

    expect(CatalogTask::query()->where('key', 'plan.interviews')->value('body_md'))->toBe('Admin body');
});

test('import with force overwrites admin edits from YAML', function () {
    $dir = syncFixture();
    editRootBody('Admin body');

    $this->artisan('catalog:import', ['path' => $dir, '--skills' => base_path('tests/Fixtures/skills'), '--force' => true])
        ->assertSuccessful();

    $task = CatalogTask::query()->where('key', 'plan.interviews')->firstOrFail();
    expect($task->body_md)->toBeNull()
        ->and($task->admin_edited_at)->toBeNull();
});

test('export writes admin edits to YAML and a re-import is a no-op', function () {
    $dir = syncFixture();
    $task = editRootBody("## Why\nAdmin body");
    PromptTemplate::query()->where('key', 'generic')->update(['admin_edited_at' => now()]);

    $this->artisan('catalog:export', ['path' => $dir])->assertSuccessful();

    $yaml = Yaml::parseFile("{$dir}/tasks/sample.yaml");
    expect($yaml['tasks'][0]['body_md'])->toBe("## Why\nAdmin body")
        ->and($yaml['tasks'][0]['children'][1]['depends_on'])->toBe([['key' => 'plan.interviews.recruit', 'kind' => 'hard']])
        ->and($yaml['tasks'][0]['resources'])->toBe([['key' => 'mom-test', 'note' => 'Read first']])
        ->and($task->fresh()->admin_edited_at)->toBeNull();

    $versions = fn (): array => [
        CatalogTask::query()->orderBy('key')->pluck('version', 'key')->all(),
        PromptTemplate::query()->pluck('version', 'key')->all(),
        Pack::query()->pluck('version', 'key')->all(),
    ];
    $before = $versions();

    $this->artisan('catalog:import', ['path' => $dir, '--check' => true])->assertSuccessful();
    $this->artisan('catalog:import', ['path' => $dir, '--skills' => base_path('tests/Fixtures/skills')])->assertSuccessful();

    expect($versions())->toBe($before)
        ->and(CatalogTask::query()->where('key', 'plan.interviews')->value('body_md'))->toBe("## Why\nAdmin body");
});

test('export puts new root tasks in a category file and keeps draft status', function () {
    $dir = syncFixture();
    $category = CatalogTask::query()->where('key', 'plan.interviews')->firstOrFail()->category;

    app(SaveCatalogTask::class)->handle(new CatalogTaskData(key: 'plan.pricing', categoryId: $category->id, title: 'Pricing'));

    $this->artisan('catalog:export', ['path' => $dir])->assertSuccessful();

    $yaml = Yaml::parseFile("{$dir}/tasks/{$category->key}.yaml");
    expect($yaml['tasks'][0])->toMatchArray(['key' => 'plan.pricing', 'category' => $category->key, 'status' => CatalogStatus::Draft->value])
        ->and(Yaml::parseFile("{$dir}/tasks/sample.yaml")['tasks'])->toHaveCount(1);

    app(ImportCatalog::class)->handle($dir);
    expect(CatalogTask::query()->where('key', 'plan.pricing')->value('status'))->toBe(CatalogStatus::Draft);
});
