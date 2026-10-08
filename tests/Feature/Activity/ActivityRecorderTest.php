<?php

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
});

test('records a user action against the subject workspace', function () {
    $activity = app(ActivityRecorder::class)->record(
        'workspace.renamed',
        $this->workspace,
        ['from' => 'A', 'to' => 'B'],
        Actor::user($this->user),
    );

    expect($activity->workspace_id)->toBe($this->workspace->id)
        ->and($activity->actor_type)->toBe(ActorType::User)
        ->and($activity->actor_id)->toBe($this->user->id)
        ->and($activity->channel)->toBe(ActivityChannel::Web)
        ->and($activity->subject_id)->toBe($this->workspace->id)
        ->and($activity->properties)->toBe(['from' => 'A', 'to' => 'B']);
});

test('defaults to the authenticated user', function () {
    $this->actingAs($this->user);

    $activity = app(ActivityRecorder::class)->record('workspace.viewed', $this->workspace);

    expect($activity->actor_id)->toBe($this->user->id);
});

test('activity is scoped to the current workspace', function () {
    $other = Workspace::factory()->create();
    app(ActivityRecorder::class)->record('workspace.viewed', $other, actor: Actor::system());

    expect(Activity::count())->toBe(0)
        ->and(Activity::withoutWorkspaceScope()->count())->toBe(1);
});
