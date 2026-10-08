<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Actions\CreateProject;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
});

test('creates a draft project with the phase default pack applied', function () {
    $task = CatalogTask::factory()->create();
    Pack::factory()->defaultFor(ProjectPhase::Selling)->withTasks($task)->create();

    $project = app(CreateProject::class)->handle($this->user, ProjectPhase::Selling, 'Acme Rockets');

    expect($project->status)->toBe(ProjectStatus::Draft)
        ->and($project->phase)->toBe(ProjectPhase::Selling)
        ->and($project->slug)->toBe('acme-rockets')
        ->and($project->owner_id)->toBe($this->user->id)
        ->and($project->workspace_id)->toBe($this->workspace->id)
        ->and($project->tasks()->count())->toBe(1);
});

test('creates an empty draft when the phase has no default pack', function () {
    $project = app(CreateProject::class)->handle($this->user, ProjectPhase::Developing, 'Solo');

    expect($project->tasks()->count())->toBe(0);
});

test('slug gets a suffix when taken in the workspace', function () {
    app(CreateProject::class)->handle($this->user, ProjectPhase::Planning, 'Acme');
    $second = app(CreateProject::class)->handle($this->user, ProjectPhase::Planning, 'Acme');

    expect($second->slug)->toBe('acme-2');
});

test('name without slug characters still gets a slug', function () {
    expect(app(CreateProject::class)->handle($this->user, ProjectPhase::Planning, '🚀🚀')->slug)->toBe('project');
});
