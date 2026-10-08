<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\MarkTaskDone;
use App\Domain\Task\Actions\RefreshTaskLocks;
use App\Domain\Task\Actions\ReopenTask;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create();
    $this->actor = Actor::user($this->user);

    $this->root = Task::factory()->forProject($this->project)->create();
    $this->a = Task::factory()->childOf($this->root)->create();
    $this->b = Task::factory()->childOf($this->root)->create();
    $this->b1 = Task::factory()->childOf($this->b)->create();
    $this->b2 = Task::factory()->childOf($this->b)->create();
    $this->action = TaskAction::factory()->forTask($this->a)->create();
});

test('completing a leaf closes its actions and rolls progress up', function () {
    app(MarkTaskDone::class)->handle($this->a, $this->actor);

    expect($this->a->fresh()->status)->toBe(TaskStatus::Done)
        ->and($this->a->fresh()->verification)->toBe(Verification::SelfReported)
        ->and($this->a->fresh()->completed_by_id)->toBe($this->user->id)
        ->and($this->action->fresh()->status)->toBe(ActionStatus::Done)
        ->and($this->root->fresh()->progress_pct)->toBe(33)   // leaves a, b1, b2 → 100/3
        ->and($this->root->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($this->b->fresh()->status)->toBe(TaskStatus::Todo);
});

test('parent becomes done when all leaves are done', function () {
    foreach ([$this->a, $this->b1, $this->b2] as $leaf) {
        app(MarkTaskDone::class)->handle($leaf, $this->actor);
    }

    expect($this->b->fresh()->status)->toBe(TaskStatus::Done)
        ->and($this->root->fresh()->status)->toBe(TaskStatus::Done)
        ->and($this->root->fresh()->progress_pct)->toBe(100);
});

test('reopening a leaf reopens done ancestors', function () {
    foreach ([$this->a, $this->b1, $this->b2] as $leaf) {
        app(MarkTaskDone::class)->handle($leaf, $this->actor);
    }

    app(ReopenTask::class)->handle($this->b1, $this->actor);

    expect($this->b1->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($this->b->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($this->b->fresh()->completed_at)->toBeNull()
        ->and($this->root->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($this->root->fresh()->progress_pct)->toBe(67);
});

test('parent tasks cannot be completed directly', function () {
    app(MarkTaskDone::class)->handle($this->root, $this->actor);
})->throws(InvalidTaskTransition::class);

test('locked tasks cannot be completed', function () {
    $this->b2->dependencies()->attach($this->b1->id, ['kind' => 'hard']);
    app(RefreshTaskLocks::class)->handle($this->project);

    app(MarkTaskDone::class)->handle($this->b2->fresh(), $this->actor);
})->throws(InvalidTaskTransition::class);

test('completing a dependency unlocks dependents and reopening re-locks them', function () {
    $this->b2->dependencies()->attach($this->b1->id, ['kind' => 'hard']);
    app(RefreshTaskLocks::class)->handle($this->project);

    app(MarkTaskDone::class)->handle($this->b1, $this->actor);
    expect($this->b2->fresh()->status)->toBe(TaskStatus::Todo);

    app(ReopenTask::class)->handle($this->b1->fresh(), $this->actor);
    expect($this->b2->fresh()->status)->toBe(TaskStatus::Locked);
});

test('completing twice is a no-op with one activity row', function () {
    app(MarkTaskDone::class)->handle($this->a, $this->actor);
    app(MarkTaskDone::class)->handle($this->a->fresh(), $this->actor);

    expect(Activity::where('event', 'task.completed')->count())->toBe(1);
});
