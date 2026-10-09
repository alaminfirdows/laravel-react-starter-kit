<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Comment\Actions\PostComment;
use App\Domain\Comment\Events\CommentPosted;
use App\Domain\Project\Broadcasting\ProjectChannel;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Events\RunFinished;
use App\Domain\Task\Events\TaskStatusChanged;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Actions\RemoveMember;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->owner)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();
    $this->task = Task::factory()->forProject($this->project)->create();
});

test('project channel is registered', function () {
    expect(Broadcast::getChannels()->keys()->all())->toContain('projects.{projectId}');
});

test('only workspace members can join the project channel', function () {
    $member = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);
    $channel = app(ProjectChannel::class);

    expect($channel->join($this->owner, $this->project->id))->toBeTrue()
        ->and($channel->join($member, $this->project->id))->toBeTrue()
        ->and($channel->join(User::factory()->create(), $this->project->id))->toBeFalse()
        ->and($channel->join($this->owner, 'missing'))->toBeFalse();

    app(RemoveMember::class)->handle($this->workspace, $member);

    expect($channel->join($member, $this->project->id))->toBeFalse();
});

test('task status change broadcasts ids only on the project channel', function () {
    Event::fake([TaskStatusChanged::class]);

    $this->task->update(['title' => 'Renamed']);
    Event::assertNotDispatched(TaskStatusChanged::class);

    $this->task->forceFill(['status' => TaskStatus::InProgress])->save();

    Event::assertDispatched(TaskStatusChanged::class, fn (TaskStatusChanged $event): bool => $event->broadcastOn()->name === "private-projects.{$this->project->id}"
        && $event->broadcastWith() === ['taskId' => $this->task->id, 'status' => 'in_progress']);
});

test('posting a comment broadcasts it', function () {
    Event::fake([CommentPosted::class]);

    $comment = app(PostComment::class)->handle($this->task, 'Looks good', Actor::user($this->owner));

    Event::assertDispatched(CommentPosted::class, fn (CommentPosted $event): bool => $event->broadcastOn()->name === "private-projects.{$this->project->id}"
        && $event->broadcastWith() === ['commentId' => $comment->id, 'taskId' => $this->task->id]);
});

test('finished run broadcasts, started run does not', function () {
    Event::fake([RunFinished::class]);
    $run = ActionRun::factory()->forAction(TaskAction::factory()->forTask($this->task)->create())->create(['status' => RunStatus::Started]);

    Event::assertNotDispatched(RunFinished::class);

    $run->forceFill(['status' => RunStatus::Succeeded, 'finished_at' => now()])->save();

    Event::assertDispatched(RunFinished::class, fn (RunFinished $event): bool => $event->broadcastWith() === ['runId' => $run->id, 'taskId' => $this->task->id, 'status' => 'succeeded']);
});

test('events use stable broadcast names', function () {
    $run = ActionRun::factory()->forAction(TaskAction::factory()->forTask($this->task)->create())->create();
    $comment = app(PostComment::class)->handle($this->task, 'Hi', Actor::user($this->owner));

    expect((new TaskStatusChanged($this->task))->broadcastAs())->toBe('task.status-changed')
        ->and((new RunFinished($run))->broadcastAs())->toBe('run.finished')
        ->and((new CommentPosted($comment))->broadcastAs())->toBe('comment.posted');
});

test('pusher connection is configured', function () {
    expect(config('broadcasting.connections.pusher.driver'))->toBe('pusher');
});

test('broadcast events dispatch only after commit', function () {
    $run = ActionRun::factory()->forAction(TaskAction::factory()->forTask($this->task)->create())->create();
    $comment = app(PostComment::class)->handle($this->task, 'Hi', Actor::user($this->owner));

    expect(new TaskStatusChanged($this->task))->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->and(new RunFinished($run))->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->and(new CommentPosted($comment))->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

test('rolled back status change does not dispatch', function () {
    Event::fake([TaskStatusChanged::class]);

    DB::beginTransaction();
    $this->task->forceFill(['status' => TaskStatus::InProgress])->save();
    DB::rollBack();

    Event::assertNotDispatched(TaskStatusChanged::class);
});

test('broadcasting auth endpoint admits members only', function () {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');

    $payload = ['channel_name' => "private-projects.{$this->project->id}", 'socket_id' => '1234.5678'];

    $this->actingAs($this->owner)->postJson('/broadcasting/auth', $payload)->assertOk();
    $this->actingAs(User::factory()->create())->postJson('/broadcasting/auth', $payload)->assertForbidden();
});
