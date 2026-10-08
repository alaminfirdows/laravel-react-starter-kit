<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    [$this->project, $this->parent, $this->leaf] = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);
        $parent = Task::factory()->forProject($project)->create(['title' => 'Parent']);
        $leaf = Task::factory()->childOf($parent)->create(['title' => 'Leaf']);

        return [$project, $parent, $leaf];
    });
    $this->actingAs($this->user);
});

test('subtask page has breadcrumb parent and prompt per action', function () {
    app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => TaskAction::factory()->forTask($this->leaf)->create());

    $this->get("/acme/projects/rocket/tasks/{$this->leaf->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/tasks/show')
            ->where('task.title', 'Leaf')
            ->where('task.parent.title', 'Parent')
            ->where('task.ancestors.0.title', 'Parent')
            ->has('task.actions.0.prompt')
            ->has('tree.groups'));
});

test('complete and reopen a leaf', function () {
    $this->post("/acme/projects/rocket/tasks/{$this->leaf->id}/completion")->assertRedirect();
    expect($this->leaf->fresh()->status)->toBe(TaskStatus::Done)
        ->and($this->parent->fresh()->status)->toBe(TaskStatus::Done);

    $this->delete("/acme/projects/rocket/tasks/{$this->leaf->id}/completion")->assertRedirect();
    expect($this->leaf->fresh()->status)->toBe(TaskStatus::Todo);
});

test('completing a parent flashes an error toast and changes nothing', function () {
    $this->post("/acme/projects/rocket/tasks/{$this->parent->id}/completion")
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error');

    expect($this->parent->fresh()->status)->toBe(TaskStatus::Todo);
});

test('viewer can read but not complete', function () {
    $viewer = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);

    $this->actingAs($viewer)->get("/acme/projects/rocket/tasks/{$this->leaf->id}")->assertOk();
    $this->actingAs($viewer)->post("/acme/projects/rocket/tasks/{$this->leaf->id}/completion")->assertForbidden();
});
