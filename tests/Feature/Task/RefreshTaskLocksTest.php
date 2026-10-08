<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RefreshTaskLocks;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

beforeEach(function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create();
    $this->dep = Task::factory()->forProject($this->project)->create();
    $this->task = Task::factory()->forProject($this->project)->create();
    $this->task->dependencies()->attach($this->dep->id, ['kind' => 'hard']);
});

test('locks while a hard dependency is open and unlocks when closed', function () {
    app(RefreshTaskLocks::class)->handle($this->project);
    expect($this->task->fresh()->status)->toBe(TaskStatus::Locked);

    $this->dep->forceFill(['status' => TaskStatus::Done])->save();
    app(RefreshTaskLocks::class)->handle($this->project);
    expect($this->task->fresh()->status)->toBe(TaskStatus::Todo);
});

test('soft dependencies never lock', function () {
    $this->task->dependencies()->updateExistingPivot($this->dep->id, ['kind' => 'soft']);

    app(RefreshTaskLocks::class)->handle($this->project);

    expect($this->task->fresh()->status)->toBe(TaskStatus::Todo);
});

test('does not touch done tasks', function () {
    $this->task->forceFill(['status' => TaskStatus::Done])->save();

    app(RefreshTaskLocks::class)->handle($this->project);

    expect($this->task->fresh()->status)->toBe(TaskStatus::Done);
});
