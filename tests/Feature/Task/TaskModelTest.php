<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();
});

test('task tree with children in order', function () {
    $root = Task::factory()->forProject($this->project)->create();
    $b = Task::factory()->childOf($root)->create(['sort_order' => 2]);
    $a = Task::factory()->childOf($root)->create(['sort_order' => 1]);

    expect($root->children->pluck('id')->all())->toBe([$a->id, $b->id])
        ->and($a->depth)->toBe(1)
        ->and($root->isLeaf())->toBeFalse()
        ->and($a->isLeaf())->toBeTrue()
        ->and($a->status)->toBe(TaskStatus::Todo);
});

test('a catalog task is copied into a project once', function () {
    $catalogTaskId = CatalogTask::factory()->create()->id;
    Task::factory()->forProject($this->project)->create(['catalog_task_id' => $catalogTaskId]);

    Task::factory()->forProject($this->project)->create(['catalog_task_id' => $catalogTaskId]);
})->throws(QueryException::class);

test('depth is limited to 0..2', function () {
    Task::factory()->forProject($this->project)->create(['depth' => 3]);
})->throws(QueryException::class);
