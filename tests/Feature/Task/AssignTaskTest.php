<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Actions\RemoveMember;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create(['name' => 'Mia']);
    $this->viewer = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->owner)->create(['slug' => 'acme']);
    $this->workspace->memberships()->create(['user_id' => $this->member->id, 'role' => WorkspaceRole::Member, 'joined_at' => now()]);
    $this->workspace->memberships()->create(['user_id' => $this->viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);
    [$this->project, $this->task] = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);

        return [$project, Task::factory()->forProject($project)->create(['title' => 'Write ICP'])];
    });
    $this->url = "/acme/projects/rocket/tasks/{$this->task->id}/assignee";
    $this->actingAs($this->owner);
});

test('assign a member records activity and shows on task page', function () {
    $this->put($this->url, ['assignee_id' => $this->member->id])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Assigned to Mia.');

    expect($this->task->fresh()->assignee_id)->toBe($this->member->id)
        ->and(Activity::withoutWorkspaceScope()->where('event', 'task.assigned')->exists())->toBeTrue();

    $this->get("/acme/projects/rocket/tasks/{$this->task->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('task.assignee.name', 'Mia')
            ->has('assignees', 2));
});

test('unassign clears assignee', function () {
    $this->task->forceFill(['assignee_id' => $this->member->id])->save();

    $this->put($this->url, ['assignee_id' => null])->assertInertiaFlash('toast.message', 'Unassigned.');

    expect($this->task->fresh()->assignee_id)->toBeNull();
});

test('viewer and non-member cannot be assigned', function () {
    $outsider = User::factory()->create();

    $this->put($this->url, ['assignee_id' => $this->viewer->id])->assertSessionHasErrors('assignee_id');
    $this->put($this->url, ['assignee_id' => $outsider->id])->assertSessionHasErrors('assignee_id');

    expect($this->task->fresh()->assignee_id)->toBeNull();
});

test('viewer cannot assign', function () {
    $this->actingAs($this->viewer)
        ->put($this->url, ['assignee_id' => $this->member->id])
        ->assertForbidden();
});

test('removing a member unassigns their tasks', function () {
    $this->task->forceFill(['assignee_id' => $this->member->id])->save();

    app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => app(RemoveMember::class)->handle($this->workspace, $this->member));

    expect($this->task->fresh()->assignee_id)->toBeNull();
});

test('my tasks lists open tasks assigned to me', function () {
    $done = app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => Task::factory()->forProject($this->project)->create(['status' => TaskStatus::Done]));
    $this->task->forceFill(['assignee_id' => $this->member->id])->save();
    $done->forceFill(['assignee_id' => $this->member->id])->save();

    $this->actingAs($this->member)
        ->get('/acme/my-tasks')
        ->assertInertia(fn (Assert $page) => $page
            ->component('tasks/mine')
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Write ICP')
            ->where('tasks.0.project.slug', 'rocket'));
});
