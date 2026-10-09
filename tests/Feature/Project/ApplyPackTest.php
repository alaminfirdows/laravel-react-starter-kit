<?php

use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Actions\ApplyPack;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Events\TaskStatusChanged;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();

    $this->root = CatalogTask::factory()->create(['title' => 'Validate', 'version' => 3]);
    $this->recruit = CatalogTask::factory()->childOf($this->root)->create(['title' => 'Recruit', 'sort_order' => 1]);
    $this->call = CatalogTask::factory()->childOf($this->root)->create(['title' => 'Call', 'sort_order' => 2]);
    DB::table('catalog_task_dependencies')->insert(['task_id' => $this->call->id, 'depends_on_id' => $this->recruit->id, 'kind' => 'hard']);
    CatalogAction::factory()->create(['catalog_task_id' => $this->recruit->id, 'title' => 'Book calls']);

    $this->pack = Pack::factory()->withTasks($this->root)->create(['version' => 2]);
});

test('copies the subtree, actions and dependencies', function () {
    $created = app(ApplyPack::class)->handle($this->project, $this->pack);

    $root = Task::where('catalog_task_id', $this->root->id)->firstOrFail();
    $recruit = Task::where('catalog_task_id', $this->recruit->id)->firstOrFail();
    $call = Task::where('catalog_task_id', $this->call->id)->firstOrFail();

    expect($created)->toBe(3)
        ->and($root->depth)->toBe(0)
        ->and($root->catalog_version)->toBe(3)
        ->and($root->category_key)->toBe($this->root->category->key)
        ->and($recruit->parent_id)->toBe($root->id)
        ->and($recruit->depth)->toBe(1)
        ->and($recruit->actions->first()->title)->toBe('Book calls')
        ->and($recruit->actions->first()->status)->toBe(ActionStatus::Pending)
        ->and($call->dependencies->first()->id)->toBe($recruit->id)
        ->and($call->status)->toBe(TaskStatus::Locked)
        ->and($recruit->status)->toBe(TaskStatus::Todo)
        ->and($this->project->packs()->first()->pack_version)->toBe(2);
});

test('blocked new tasks start locked without per-task events', function () {
    Event::fake([TaskStatusChanged::class]);

    app(ApplyPack::class)->handle($this->project, $this->pack);

    expect(Task::where('catalog_task_id', $this->call->id)->firstOrFail()->status)->toBe(TaskStatus::Locked);
    Event::assertNotDispatched(TaskStatusChanged::class);
    $this->assertDatabaseMissing('activity_log', ['event' => 'task.locked']);
});

test('applying twice is idempotent', function () {
    app(ApplyPack::class)->handle($this->project, $this->pack);
    $created = app(ApplyPack::class)->handle($this->project, $this->pack);

    expect($created)->toBe(0)
        ->and(Task::count())->toBe(3)
        ->and($this->project->packs()->count())->toBe(1);
});

test('records activity', function () {
    app(ApplyPack::class)->handle($this->project, $this->pack);

    $this->assertDatabaseHas('activity_log', [
        'project_id' => $this->project->id,
        'event' => 'project.pack_applied',
    ]);
});

test('skips unpublished catalog tasks', function () {
    $this->call->forceFill(['status' => 'archived'])->save();

    expect(app(ApplyPack::class)->handle($this->project, $this->pack))->toBe(2);
});
