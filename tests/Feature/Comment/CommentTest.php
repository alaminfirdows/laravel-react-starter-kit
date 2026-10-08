<?php

use App\Domain\Comment\Models\Comment;
use App\Domain\Comment\Notifications\MentionedInComment;
use App\Domain\Comment\Support\MentionParser;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->create(['name' => 'Olivia Owner']);
    $this->member = User::factory()->create(['name' => 'Mia Stone', 'email' => 'mia@example.com']);
    $this->viewer = User::factory()->create(['name' => 'Vic']);
    $this->workspace = Workspace::factory()->ownedBy($this->owner)->create(['slug' => 'acme']);
    $this->workspace->memberships()->create(['user_id' => $this->member->id, 'role' => WorkspaceRole::Member, 'joined_at' => now()]);
    $this->workspace->memberships()->create(['user_id' => $this->viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);
    $this->task = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);

        return Task::factory()->forProject($project)->create(['title' => 'Write ICP']);
    });
    $this->url = "/acme/projects/rocket/tasks/{$this->task->id}/comments";
    $this->actingAs($this->owner);
});

test('parser finds handles and ignores emails', function () {
    expect(app(MentionParser::class)->handles('Hi @Mia, cc @vic. Mail a@b.com @mia'))->toBe(['mia', 'vic']);
});

test('post comment notifies mentioned members only', function () {
    $outsider = User::factory()->create(['name' => 'Otto']);

    $this->post($this->url, ['body_md' => 'Can @MiaStone and @vic review? Not @otto, not @nobody. Me: @OliviaOwner'])
        ->assertRedirect();

    $comment = Comment::withoutWorkspaceScope()->sole();
    expect($comment->author_id)->toBe($this->owner->id)
        ->and($comment->body_md)->toContain('@otto');

    Notification::assertSentTo([$this->member, $this->viewer], MentionedInComment::class);
    Notification::assertNotSentTo($outsider, MentionedInComment::class);
    Notification::assertNotSentTo($this->owner, MentionedInComment::class);
    Notification::assertCount(2);
});

test('mention by email name works', function () {
    $this->post($this->url, ['body_md' => 'ping @mia']);

    Notification::assertSentTo($this->member, MentionedInComment::class,
        fn (MentionedInComment $n): bool => $n->toArray($this->member)['title'] === 'Olivia Owner mentioned you on Write ICP');
});

test('viewer cannot post a comment', function () {
    $this->actingAs($this->viewer)->post($this->url, ['body_md' => 'hello'])->assertForbidden();

    expect(Comment::withoutWorkspaceScope()->count())->toBe(0);
});

test('empty comment is rejected', function () {
    $this->post($this->url, ['body_md' => ''])->assertSessionHasErrors('body_md');
});

test('resolve and reopen a comment, listed on task page', function () {
    $comment = app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => Comment::factory()->forTask($this->task)->create(['author_id' => $this->member->id, 'body_md' => 'First']));

    $this->post("{$this->url}/{$comment->id}/resolution")->assertRedirect();
    expect($comment->fresh()->resolved_at)->not->toBeNull()
        ->and($comment->fresh()->resolved_by_id)->toBe($this->owner->id);

    $this->get("/acme/projects/rocket/tasks/{$this->task->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.0.bodyMd', 'First')
            ->where('comments.0.authorName', 'Mia Stone')
            ->whereNot('comments.0.resolvedAt', null));

    $this->delete("{$this->url}/{$comment->id}/resolution")->assertRedirect();
    expect($comment->fresh()->resolved_at)->toBeNull();
});

test('comment of another task is not found', function () {
    $other = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $task = Task::factory()->forProject($this->task->project)->create();

        return Comment::factory()->forTask($task)->create();
    });

    $this->post("{$this->url}/{$other->id}/resolution")->assertNotFound();
});
