<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\DecideApproval;
use App\Domain\Task\Actions\RequestApproval;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    [$this->project, $this->task] = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);

        return [$project, Task::factory()->forProject($project)->create(['title' => 'Pricing'])];
    });
    $this->actingAs($this->user);
});

function requestApproval(Workspace $workspace, Task $task, User $user, string $actionTitle): Approval
{
    return app(WorkspaceDiscoveryService::class)->runAs($workspace, function () use ($task, $user, $actionTitle) {
        $action = TaskAction::factory()->forTask($task)->create(['title' => $actionTitle]);

        return app(RequestApproval::class)->handle($action, Actor::agent($user, 'Claude'), 'Check tiers');
    });
}

test('page lists pending approvals with their task, history is deferred', function () {
    $pending = requestApproval($this->workspace, $this->task, $this->user, 'Draft tiers');
    $decided = requestApproval($this->workspace, $this->task, $this->user, 'Draft copy');
    app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => app(DecideApproval::class)->handle($decided, Actor::user($this->user), false, 'Too long'));

    $this->get('/acme/projects/rocket/approvals')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/approvals/index')
            ->where('pendingApprovals', 1)
            ->has('pending', 1)
            ->where('pending.0.id', $pending->id)
            ->where('pending.0.subject.title', 'Draft tiers')
            ->where('pending.0.subject.taskTitle', 'Pricing')
            ->missing('decided')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('decided', 1)
                ->where('decided.0.status', 'rejected')
                ->where('decided.0.decisionNote', 'Too long')));
});

test('approving with a note from the page clears the badge', function () {
    $approval = requestApproval($this->workspace, $this->task, $this->user, 'Draft tiers');

    $this->from('/acme/projects/rocket/approvals')
        ->post("/acme/projects/rocket/approvals/{$approval->id}/decision", ['approve' => true, 'note' => 'Looks good'])
        ->assertRedirect('/acme/projects/rocket/approvals');

    expect($approval->refresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->decision_note)->toBe('Looks good');

    $this->get('/acme/projects/rocket/approvals')
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingApprovals', 0)
            ->has('pending', 0));
});

test('approvals of other projects are not listed', function () {
    $otherTask = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, fn () => Task::factory()
        ->forProject(Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'other']))
        ->create());
    requestApproval($this->workspace, $otherTask, $this->user, 'Elsewhere');

    $this->get('/acme/projects/rocket/approvals')
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingApprovals', 0)
            ->has('pending', 0));
});
