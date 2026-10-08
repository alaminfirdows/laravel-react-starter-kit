<?php

use App\Ai\Agents\DraftDocumentAgent;
use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RequestApproval;
use App\Domain\Task\Actions\RunActionInApp;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Task\Notifications\ActionFailed;
use App\Domain\Task\Notifications\ApprovalRequested;
use App\Domain\Task\Notifications\RunFinished;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket', 'website_url' => 'https://acme.test']);
    $this->task = Task::factory()->forProject($this->project)->create();
    $this->action = TaskAction::factory()->forTask($this->task)->appAi()->create(['status' => ActionStatus::Ready, 'title' => 'Draft ICP']);
});

function addMember(Workspace $workspace, WorkspaceRole $role): User
{
    $member = User::factory()->create();
    $workspace->memberships()->create(['user_id' => $member->id, 'role' => $role, 'joined_at' => now()]);

    return $member;
}

test('approval request notifies members who can decide, not viewers', function () {
    Notification::fake();
    $member = addMember($this->workspace, WorkspaceRole::Member);
    $viewer = addMember($this->workspace, WorkspaceRole::Viewer);

    app(RequestApproval::class)->handle($this->action, Actor::agent($this->user, 'Claude'), 'Check tiers');

    Notification::assertSentTo([$this->user, $member], ApprovalRequested::class);
    Notification::assertNotSentTo($viewer, ApprovalRequested::class);
});

test('in-app run tells the starter it finished, or failed', function () {
    Notification::fake();
    DraftDocumentAgent::fake([['output_md' => '# Draft', 'outputs' => []]]);

    app(RunActionInApp::class)->handle($this->action, $this->user);

    Notification::assertSentTo($this->user, RunFinished::class, function (RunFinished $notification) {
        $data = $notification->toArray($this->user);

        return $data['title'] === 'Run finished: Draft ICP'
            && $data['url'] === route('projects.tasks.show', ['workspace' => 'acme', 'project' => 'rocket', 'task' => $this->task->id]);
    });
    Notification::assertNotSentTo($this->user, ActionFailed::class);

    $check = TaskAction::factory()->forTask($this->task)->create(['executor' => Executor::AppSystem, 'status' => ActionStatus::Ready, 'config' => ['check' => 'https']]);
    Http::fake(['https://acme.test/' => Http::response('down', 500)]);

    app(RunActionInApp::class)->handle($check, $this->user);

    Notification::assertSentTo($this->user, ActionFailed::class, fn (ActionFailed $notification) => $notification->run->task_action_id === $check->id);
});

test('a run waiting for approval sends only the approval notification', function () {
    Notification::fake();
    $this->action->forceFill(['requires_approval' => true])->save();
    DraftDocumentAgent::fake([['output_md' => '# Draft', 'outputs' => []]]);

    app(RunActionInApp::class)->handle($this->action, $this->user);

    Notification::assertSentTo($this->user, ApprovalRequested::class);
    Notification::assertNotSentTo($this->user, RunFinished::class);
});

test('bell shows unread notifications; reading one opens its task', function () {
    app(RequestApproval::class)->handle($this->action, Actor::agent($this->user, 'Claude'), 'Check tiers');
    $notification = $this->user->unreadNotifications()->sole();

    $this->actingAs($this->user)
        ->get('/acme/projects/rocket/approvals')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread', 1)
            ->where('notifications.latest.0.title', 'Approval requested: Draft ICP')
            ->where('notifications.latest.0.project', $this->project->name));

    $this->post("/notifications/{$notification->id}/read")
        ->assertRedirect(route('projects.tasks.show', ['workspace' => 'acme', 'project' => 'rocket', 'task' => $this->task->id]));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('mark all read, and no access to other users notifications', function () {
    app(RequestApproval::class)->handle($this->action, Actor::agent($this->user, 'Claude'), 'Check tiers');
    $notification = $this->user->notifications()->sole();

    $this->actingAs(User::factory()->create())
        ->post("/notifications/{$notification->id}/read")
        ->assertNotFound();

    $this->actingAs($this->user)->post('/notifications/read')->assertRedirect();

    expect($this->user->unreadNotifications()->count())->toBe(0);
});
