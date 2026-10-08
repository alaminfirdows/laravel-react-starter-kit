<?php

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Olivia']);
    $this->member = User::factory()->create();
    $this->viewer = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->owner)->create(['slug' => 'acme']);
    $this->workspace->memberships()->create(['user_id' => $this->member->id, 'role' => WorkspaceRole::Member, 'joined_at' => now()]);
    $this->workspace->memberships()->create(['user_id' => $this->viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);

    app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $this->project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket', 'name' => 'Rocket']);
        $other = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'other']);
        $recorder = app(ActivityRecorder::class);

        $recorder->record('task.completed', Task::factory()->forProject($this->project)->create(), [], Actor::user($this->owner));
        $recorder->record('task.started', Task::factory()->forProject($this->project)->create(), [], Actor::agent($this->owner, 'Claude'));
        $recorder->record('check.passed', $this->project, [], Actor::system());
        $recorder->record('task.completed', Task::factory()->forProject($other)->create(), [], Actor::user($this->owner));
    });
});

test('project log lists only project activity for a viewer', function () {
    $this->actingAs($this->viewer)
        ->get('/acme/projects/rocket/activity')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/activity')
            ->has('activity.data', 3)
            ->where('activity.data.0.event', 'check.passed')
            ->where('filters', ['actor' => null, 'channel' => null, 'event' => null])
            ->has('options.events', 3)
        );
});

test('project log filters by actor, channel and event', function (array $query, int $count) {
    $this->actingAs($this->owner)
        ->get('/acme/projects/rocket/activity?'.http_build_query($query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('activity.data', $count));
})->with([
    'system actor' => [['actor' => 'system'], 1],
    'mcp channel' => [['channel' => 'mcp'], 1],
    'event' => [['event' => 'task.completed'], 1],
    'combined' => [['event' => 'task.completed', 'channel' => 'mcp'], 0],
]);

test('project log filters by user actor', function () {
    $this->actingAs($this->owner)
        ->get('/acme/projects/rocket/activity?actor='.$this->owner->id)
        ->assertInertia(fn (Assert $page) => $page
            ->has('activity.data', 2)
            ->where('activity.data.0.actorName', 'Olivia')
        );
});

test('invalid channel filter is rejected', function () {
    $this->actingAs($this->owner)
        ->get('/acme/projects/rocket/activity?channel=fax')
        ->assertSessionHasErrors('channel');
});

test('admin sees workspace activity across projects', function () {
    $this->actingAs($this->owner)
        ->get('/acme/settings/activity')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/settings/activity')
            ->has('activity.data', 4)
            ->where('activity.data.1.project.name', 'Rocket')
        );
});

test('non admin members cannot see workspace activity', function (string $role) {
    $this->actingAs($this->{$role})
        ->get('/acme/settings/activity')
        ->assertForbidden();
})->with(['member', 'viewer']);

test('outsider is redirected away from project activity', function () {
    $this->actingAs(User::factory()->create())
        ->get('/acme/projects/rocket/activity')
        ->assertRedirect();
});
