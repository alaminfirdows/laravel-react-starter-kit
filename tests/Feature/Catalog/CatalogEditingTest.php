<?php

use App\Domain\Catalog\Actions\PublishCatalogTask;
use App\Domain\Catalog\Actions\SaveCatalogAction;
use App\Domain\Catalog\Actions\SaveCatalogTask;
use App\Domain\Catalog\Actions\SavePack;
use App\Domain\Catalog\Actions\SavePromptTemplate;
use App\Domain\Catalog\Data\CatalogActionData;
use App\Domain\Catalog\Data\CatalogTaskData;
use App\Domain\Catalog\Data\PackData;
use App\Domain\Catalog\Data\PromptTemplateData;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;

function taskData(CatalogCategory $category, array $overrides = []): CatalogTaskData
{
    return new CatalogTaskData(...[
        'key' => 'define-icp',
        'categoryId' => $category->id,
        'title' => 'Define ICP',
        'bodyMd' => 'Who buys?',
        ...$overrides,
    ]);
}

test('new catalog task starts as draft at version 1', function () {
    $task = app(SaveCatalogTask::class)->handle(taskData(CatalogCategory::factory()->create()));

    expect($task->status)->toBe(CatalogStatus::Draft)
        ->and($task->version)->toBe(1)
        ->and($task->admin_edited_at)->not->toBeNull()
        ->and($task->published_at)->toBeNull();
});

test('content change bumps version, unchanged save does not', function () {
    $category = CatalogCategory::factory()->create();
    $task = CatalogTask::factory()->create(['category_id' => $category->id, 'title' => 'Define ICP', 'body_md' => 'Old', 'version' => 3]);
    $save = app(SaveCatalogTask::class);

    $save->handle(taskData($category, ['bodyMd' => 'New']), $task);
    expect($task->fresh()->version)->toBe(4);

    $save->handle(taskData($category, ['bodyMd' => 'New']), $task->fresh());
    expect($task->fresh()->version)->toBe(4)
        ->and($task->fresh()->body_md)->toBe('New');
});

test('child task inherits parent category and depth is capped at 3 levels', function () {
    $category = CatalogCategory::factory()->create();
    $root = CatalogTask::factory()->create(['category_id' => $category->id]);
    $child = CatalogTask::factory()->childOf($root)->create();
    $grandchild = CatalogTask::factory()->childOf($child)->create();
    $save = app(SaveCatalogTask::class);

    $new = $save->handle(taskData(CatalogCategory::factory()->create(), ['key' => 'sub', 'parentId' => $root->id]));
    expect($new->category_id)->toBe($category->id)
        ->and($new->parent_id)->toBe($root->id);

    $save->handle(taskData($category, ['key' => 'too-deep', 'parentId' => $grandchild->id]));
})->throws(InvalidArgumentException::class);

test('action change bumps the task version and stamps the action', function () {
    $task = CatalogTask::factory()->create(['version' => 2]);
    $data = new CatalogActionData(key: 'draft', title: 'Draft ICP', type: ActionType::Ai, executor: Executor::AppAi);
    $save = app(SaveCatalogAction::class);

    $action = $save->handle($task, $data);
    expect($task->fresh()->version)->toBe(3)
        ->and($action->version)->toBe(3);

    $save->handle($task->fresh(), $data, $action->fresh());
    expect($task->fresh()->version)->toBe(3);
});

test('prompt template versions on change only', function () {
    $prompt = PromptTemplate::factory()->create(['version' => 1]);
    $data = new PromptTemplateData(key: $prompt->key, title: 'ICP prompt', fullMd: 'Write the ICP for {{ project.name }}');
    $save = app(SavePromptTemplate::class);

    $save->handle($data, $prompt);
    $save->handle($data, $prompt->fresh());

    expect($prompt->fresh()->version)->toBe(2)
        ->and($prompt->fresh()->full_md)->toBe('Write the ICP for {{ project.name }}');
});

test('default pack replaces the previous default of its phase and items version the pack', function () {
    $old = Pack::factory()->defaultFor(ProjectPhase::Planning)->create();
    $other = Pack::factory()->defaultFor(ProjectPhase::Selling)->create();
    $tasks = CatalogTask::factory()->count(2)->create();
    $save = app(SavePack::class);
    $data = fn (array $ids) => new PackData(
        key: 'planning-plus',
        name: 'Planning plus',
        phase: ProjectPhase::Planning,
        items: array_map(fn (int $id) => ['catalog_task_id' => $id, 'include_subtree' => true], $ids),
        isDefault: true,
    );

    $pack = $save->handle($data([$tasks[0]->id, $tasks[1]->id]));
    $save->handle($data([$tasks[0]->id, $tasks[1]->id]), $pack->fresh());
    expect($pack->fresh()->version)->toBe(1);

    $save->handle($data([$tasks[1]->id]), $pack->fresh());

    expect($pack->fresh()->version)->toBe(2)
        ->and($pack->items()->pluck('catalog_task_id')->all())->toBe([$tasks[1]->id])
        ->and(Pack::defaultForPhase(ProjectPhase::Planning)->id)->toBe($pack->id)
        ->and($old->fresh()->is_default)->toBeFalse()
        ->and($other->fresh()->is_default)->toBeTrue();
});

test('publish makes the task live', function () {
    $task = CatalogTask::factory()->create(['status' => CatalogStatus::Draft, 'published_at' => null]);

    app(PublishCatalogTask::class)->handle($task);

    expect($task->fresh()->status)->toBe(CatalogStatus::Published)
        ->and($task->fresh()->published_at)->not->toBeNull();
});
