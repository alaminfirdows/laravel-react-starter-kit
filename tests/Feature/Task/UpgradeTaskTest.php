<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Actions\DiffCatalogVersion;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Actions\ApplyPack;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\UpgradeTaskFromCatalog;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);

    $this->catalogTask = CatalogTask::factory()->create(['title' => 'Interviews', 'body_md' => 'Old body', 'summary' => 'Old summary', 'version' => 1]);
    app(ApplyPack::class)->handle($this->project, Pack::factory()->withTasks($this->catalogTask)->create());
    $this->task = Task::query()->where('catalog_task_id', $this->catalogTask->id)->firstOrFail();
});

function bumpCatalog(CatalogTask $catalogTask, array $attributes): void
{
    $catalogTask->forceFill([...$attributes, 'version' => $catalogTask->version + 1])->save();
}

test('apply pack stores the catalog snapshot', function () {
    expect($this->task->catalog_snapshot)
        ->toMatchArray(['title' => 'Interviews', 'body_md' => 'Old body', 'priority' => 'p2']);
});

test('founder and catalog both changed the body: conflict, default keeps founder version', function () {
    $this->task->forceFill(['body_md' => 'Founder body'])->save();
    bumpCatalog($this->catalogTask, ['body_md' => 'Catalog body', 'summary' => 'New summary']);

    $diffs = collect(app(DiffCatalogVersion::class)->handle($this->task->fresh()))->keyBy('field');

    expect($diffs->keys()->all())->toEqualCanonicalizing(['body_md', 'summary'])
        ->and($diffs['body_md']->isConflict)->toBeTrue()
        ->and($diffs['body_md']->founder)->toBe('Founder body')
        ->and($diffs['summary']->isConflict)->toBeFalse();

    $task = app(UpgradeTaskFromCatalog::class)->handle($this->task->fresh(), ['summary'], Actor::system());

    expect($task->body_md)->toBe('Founder body')
        ->and($task->summary)->toBe('New summary')
        ->and($task->catalog_version)->toBe(2)
        ->and($task->has_catalog_update)->toBeFalse()
        ->and($task->catalog_snapshot['body_md'])->toBe('Catalog body');

    expect(app(DiffCatalogVersion::class)->handle($task))->toBe([]);
});

test('founder edit with unchanged catalog field is not listed', function () {
    $this->task->forceFill(['title' => 'My interviews'])->save();
    bumpCatalog($this->catalogTask, ['priority_default' => TaskPriority::P0]);

    $diffs = app(DiffCatalogVersion::class)->handle($this->task->fresh());

    expect($diffs)->toHaveCount(1)
        ->and($diffs[0]->field)->toBe('priority')
        ->and($diffs[0]->isConflict)->toBeFalse();

    $task = app(UpgradeTaskFromCatalog::class)->handle($this->task->fresh(), ['priority'], Actor::system());

    expect($task->priority)->toBe(TaskPriority::P0)
        ->and($task->title)->toBe('My interviews');
});

test('task without snapshot treats every differing field as conflict', function () {
    $this->task->forceFill(['catalog_snapshot' => null])->save();
    bumpCatalog($this->catalogTask, ['body_md' => 'Catalog body']);

    $diffs = app(DiffCatalogVersion::class)->handle($this->task->fresh());

    expect($diffs)->toHaveCount(1)
        ->and($diffs[0]->isConflict)->toBeTrue();
});

test('upgrade without a newer version is refused', function () {
    app(UpgradeTaskFromCatalog::class)->handle($this->task, [], Actor::system());
})->throws(InvalidTaskTransition::class);

test('task page loads the diff on demand and upgrade applies chosen fields', function () {
    $this->task->forceFill(['body_md' => 'Founder body', 'has_catalog_update' => true])->save();
    bumpCatalog($this->catalogTask, ['body_md' => 'Catalog body']);
    $url = "/acme/projects/rocket/tasks/{$this->task->id}";

    $this->actingAs($this->user)->get($url)
        ->assertInertia(fn (Assert $page) => $page
            ->where('task.hasCatalogUpdate', true)
            ->missing('catalogDiff')
            ->reloadOnly('catalogDiff', fn (Assert $reload) => $reload
                ->where('catalogDiff.0.field', 'body_md')
                ->where('catalogDiff.0.isConflict', true)
                ->where('catalogDiff.0.catalog', 'Catalog body')
            )
        );

    $this->from($url)->post("{$url}/catalog-upgrade", ['fields' => ['body_md']])->assertRedirect($url);

    expect($this->task->fresh())
        ->body_md->toBe('Catalog body')
        ->has_catalog_update->toBeFalse();
});

test('upgrade rejects unknown fields and other workspaces', function () {
    bumpCatalog($this->catalogTask, ['body_md' => 'Catalog body']);
    $url = "/acme/projects/rocket/tasks/{$this->task->id}/catalog-upgrade";

    $this->actingAs($this->user)->post($url, ['fields' => ['status']])->assertSessionHasErrors('fields.0');
    $this->actingAs(User::factory()->create())->post($url, ['fields' => ['body_md']])->assertForbidden();

    expect($this->task->fresh()->body_md)->toBe('Old body');
});
