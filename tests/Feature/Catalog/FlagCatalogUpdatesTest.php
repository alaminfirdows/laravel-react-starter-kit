<?php

use App\Domain\Catalog\Actions\ImportCatalog;
use App\Domain\Catalog\Actions\PublishCatalogTask;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Jobs\FlagCatalogUpdates;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Facades\File;

function linkedTask(CatalogTask $catalogTask, int $version, bool $flagged = false): Task
{
    return Task::factory()->forProject(Project::factory()->create())->create([
        'catalog_task_id' => $catalogTask->id,
        'catalog_version' => $version,
        'has_catalog_update' => $flagged,
    ]);
}

function freshTask(Task $task): Task
{
    return Task::withoutWorkspaceScope()->findOrFail($task->id);
}

test('publish flags only tasks holding an older version', function () {
    $catalogTask = CatalogTask::factory()->create(['version' => 3]);
    $older = linkedTask($catalogTask, 2);
    $current = linkedTask($catalogTask, 3, flagged: true);
    $unrelated = linkedTask(CatalogTask::factory()->create(['version' => 5]), 1);

    app(PublishCatalogTask::class)->handle($catalogTask);

    expect(freshTask($older)->has_catalog_update)->toBeTrue()
        ->and(freshTask($current)->has_catalog_update)->toBeFalse()
        ->and(freshTask($unrelated)->has_catalog_update)->toBeFalse();
});

test('draft catalog task does not flag projects', function () {
    $catalogTask = CatalogTask::factory()->create(['version' => 2, 'status' => CatalogStatus::Draft]);
    $task = linkedTask($catalogTask, 1);

    FlagCatalogUpdates::dispatchSync($catalogTask->id);

    expect(freshTask($task)->has_catalog_update)->toBeFalse();
});

test('archived catalog task turns project task custom and clears flag', function () {
    $catalogTask = CatalogTask::factory()->create(['version' => 2, 'status' => CatalogStatus::Archived]);
    $task = linkedTask($catalogTask, 1, flagged: true);

    FlagCatalogUpdates::dispatchSync($catalogTask->id);

    expect(freshTask($task))
        ->catalog_task_id->toBeNull()
        ->has_catalog_update->toBeFalse();
});

test('deleted catalog task keeps project task and clears flag', function () {
    $catalogTask = CatalogTask::factory()->create(['version' => 2]);
    $task = linkedTask($catalogTask, 1, flagged: true);
    $catalogTaskId = $catalogTask->id;
    $catalogTask->delete();

    FlagCatalogUpdates::dispatchSync($catalogTaskId);

    expect(freshTask($task))
        ->catalog_task_id->toBeNull()
        ->has_catalog_update->toBeFalse();
});

test('import with a version bump flags project tasks', function () {
    $dir = storage_path('framework/testing/catalog-flag-'.uniqid());
    File::copyDirectory(base_path('tests/Fixtures/catalog'), $dir);
    $skills = base_path('tests/Fixtures/skills');
    app(ImportCatalog::class)->handle($dir, $skills);

    $catalogTask = CatalogTask::query()->where('key', 'plan.interviews')->firstOrFail();
    $task = linkedTask($catalogTask, $catalogTask->version);

    File::put("{$dir}/tasks/sample.yaml", str_replace('title: Run interviews', 'title: Run customer interviews', File::get("{$dir}/tasks/sample.yaml")));
    app(ImportCatalog::class)->handle($dir, $skills);

    expect($catalogTask->fresh()->version)->toBe($catalogTask->version + 1)
        ->and(freshTask($task)->has_catalog_update)->toBeTrue();

    File::deleteDirectory($dir);
});
