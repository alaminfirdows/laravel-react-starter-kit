<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Jobs\RollupProgressJob;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    $this->actingAs($this->user);
});

test('lists projects of the workspace', function () {
    app(WorkspaceDiscoveryService::class)->runAs($this->workspace, fn () => Project::factory()->forWorkspace($this->workspace)->create(['name' => 'Rocket']));

    $this->get('/acme/projects')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects', 1)
            ->where('projects.0.name', 'Rocket'));
});

test('create page lists the three phases', function () {
    $this->get('/acme/projects/create')
        ->assertInertia(fn (Assert $page) => $page->component('projects/create')->has('phases', 3));
});

test('store creates a draft with tasks and goes to setup', function () {
    $task = CatalogTask::factory()->create();
    Pack::factory()->defaultFor(ProjectPhase::Developing)->withTasks($task)->create();

    $this->post('/acme/projects', ['phase' => 'developing', 'name' => 'Rocket'])
        ->assertRedirect('/acme/projects/rocket/setup/identity');

    $project = Project::forWorkspace($this->workspace)->firstOrFail();
    expect($project->isDraft())->toBeTrue()
        ->and($project->tasks()->count())->toBe(1);
});

test('store validates phase and name', function () {
    $this->post('/acme/projects', ['phase' => 'dreaming', 'name' => ''])
        ->assertSessionHasErrors(['phase', 'name']);
});

test('overview renders tree and next task', function () {
    $project = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);
        Task::factory()->forProject($project)->create(['title' => 'First']);

        return $project;
    });

    $this->get('/acme/projects/rocket')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/overview')
            ->where('project.slug', 'rocket')
            ->has('tree.groups', 1)
            ->where('nextTask.title', 'First'));
});

test('index shows cached mean leaf progress per project', function () {
    app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create();
        $parent = Task::factory()->forProject($project)->create();
        Task::factory()->childOf($parent)->done()->create();
        Task::factory()->childOf($parent)->create();
        (new RollupProgressJob($project->id))->handle();
    });

    $this->get('/acme/projects')
        ->assertInertia(fn (Assert $page) => $page->where('projects.0.progressPct', 50));
});

test('overview loads activity only on request', function () {
    $this->post('/acme/projects', ['phase' => 'planning', 'name' => 'Rocket']);

    $this->get('/acme/projects/rocket')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('activity')
            ->reloadOnly('activity', fn (Assert $reload) => $reload
                ->where('activity.0.event', 'project.created')));
});
