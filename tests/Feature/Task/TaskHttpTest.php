<?php

use App\Ai\Jobs\RunAppAiActionJob;
use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RequestApproval;
use App\Domain\Task\Actions\StartAction;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
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

test('action shows deep link, runs, evidence and pending approval while a run is active', function () {
    $action = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $action = TaskAction::factory()->forTask($this->leaf)->create();
        app(StartAction::class)->handle($action, Actor::agent($this->user, 'Claude'), RunChannel::Mcp);
        app(RequestApproval::class)->handle($action->refresh(), Actor::agent($this->user, 'Claude'), 'Check tiers');

        return $action;
    });

    $this->get("/acme/projects/rocket/tasks/{$this->leaf->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('task.hasActiveRun', false)
            ->where('task.actions.0.deepLink.target', 'chat')
            ->where('task.actions.0.deepLink.url', fn (?string $url) => str_starts_with((string) $url, 'claude://'))
            ->where('task.actions.0.runs.0.clientName', 'Claude')
            ->where('task.actions.0.pendingApproval.summaryMd', 'Check tiers')
            ->has('task.actions.0.evidence', 0));

    expect($action->refresh()->status)->toBe(ActionStatus::AwaitingApproval);
});

test('owner approves a pending approval; viewer cannot', function () {
    $approval = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $action = TaskAction::factory()->forTask($this->leaf)->create();

        return app(RequestApproval::class)->handle($action, Actor::agent($this->user, 'Claude'), 'Check tiers');
    });

    $viewer = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);
    $this->actingAs($viewer)->post("/acme/projects/rocket/approvals/{$approval->id}/decision", ['approve' => true])->assertForbidden();

    $this->actingAs($this->user)->post("/acme/projects/rocket/approvals/{$approval->id}/decision", ['approve' => true])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    expect($approval->refresh()->status)->toBe(ApprovalStatus::Approved);
});

test('approval of another project is not found', function () {
    $approval = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $other = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'other']);
        $action = TaskAction::factory()->forTask(Task::factory()->forProject($other)->create())->create();

        return app(RequestApproval::class)->handle($action, Actor::agent($this->user, 'Claude'), 'Check');
    });

    $this->post("/acme/projects/rocket/approvals/{$approval->id}/decision", ['approve' => true])->assertNotFound();
});

test('run with AI starts an in-app run and the page polls it', function () {
    Queue::fake();
    $action = app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => TaskAction::factory()->forTask($this->leaf)->appAi()->create(['status' => ActionStatus::Ready]));

    $this->get("/acme/projects/rocket/tasks/{$this->leaf->id}")
        ->assertInertia(fn (Assert $page) => $page->where('task.actions.0.runsInApp', true));

    $this->post("/acme/projects/rocket/tasks/{$this->leaf->id}/actions/{$action->id}/runs")
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'success');

    Queue::assertPushed(RunAppAiActionJob::class);
    expect($action->refresh()->status)->toBe(ActionStatus::Running);

    $this->get("/acme/projects/rocket/tasks/{$this->leaf->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('task.hasActiveRun', true)
            ->where('task.actions.0.runs.0.channel', RunChannel::AppAi->value));

    $this->post("/acme/projects/rocket/tasks/{$this->leaf->id}/actions/{$action->id}/runs")
        ->assertInertiaFlash('toast.type', 'error');
    Queue::assertPushed(RunAppAiActionJob::class, 1);
});

test('run refuses manual actions, viewers and actions of other tasks', function () {
    [$manual, $foreign] = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, fn () => [
        TaskAction::factory()->forTask($this->leaf)->create(),
        TaskAction::factory()->forTask($this->parent)->appAi()->create(['status' => ActionStatus::Ready]),
    ]);

    $this->post("/acme/projects/rocket/tasks/{$this->leaf->id}/actions/{$manual->id}/runs")
        ->assertInertiaFlash('toast.type', 'error');
    $this->post("/acme/projects/rocket/tasks/{$this->leaf->id}/actions/{$foreign->id}/runs")
        ->assertNotFound();

    $viewer = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);
    $this->actingAs($viewer)->post("/acme/projects/rocket/tasks/{$this->parent->id}/actions/{$foreign->id}/runs")
        ->assertForbidden();
});
