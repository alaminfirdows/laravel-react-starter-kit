<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
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
    [$this->task, $this->recurring] = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, function () {
        $project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);
        $task = Task::factory()->forProject($project)->create();
        $recurring = TaskAction::factory()->forTask($task)->create([
            'type' => ActionType::Scheduled,
            'executor' => Executor::AppSystem,
            'status' => ActionStatus::Done,
            'completed_at' => now(),
            'config' => ['check' => 'https', 'schedule' => ['cron' => '* * * * *']],
        ]);

        return [$task, $recurring];
    });
    $this->actingAs($this->user);
});

function stopScheduleUrl(Task $task, TaskAction $action): string
{
    return "/acme/projects/rocket/tasks/{$task->id}/actions/{$action->id}/schedule";
}

test('an editor stops a recurring action and it is not picked up again', function (ActionStatus $status) {
    Queue::fake();
    $this->recurring->forceFill(['status' => $status])->save();

    $this->get("/acme/projects/rocket/tasks/{$this->task->id}")
        ->assertInertia(fn (Assert $page) => $page->where('task.actions.0.isRecurring', true));

    $this->delete(stopScheduleUrl($this->task, $this->recurring))->assertRedirect();

    expect($this->recurring->fresh()->status)->toBe(ActionStatus::Skipped);
    $activity = Activity::withoutGlobalScopes()->where('event', 'action.schedule_stopped')->sole();
    expect($activity->properties)->toBe(['from' => $status->value])
        ->and($activity->actor_id)->toBe($this->user->id);

    $this->travel(10)->minutes();
    $this->artisan('actions:process-scheduled')->assertSuccessful();

    expect($this->recurring->runs()->count())->toBe(0)
        ->and($this->recurring->fresh()->status)->toBe(ActionStatus::Skipped);
})->with([ActionStatus::Done, ActionStatus::Failed]);

test('a viewer cannot stop a schedule', function () {
    $viewer = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);

    $this->actingAs($viewer)->delete(stopScheduleUrl($this->task, $this->recurring))->assertForbidden();

    expect($this->recurring->fresh()->status)->toBe(ActionStatus::Done);
});

test('a running or non recurring action is not stopped', function () {
    $this->recurring->forceFill(['status' => ActionStatus::Running])->save();
    $oneOff = app(WorkspaceDiscoveryService::class)->runAs($this->workspace, fn () => TaskAction::factory()->forTask($this->task)->create([
        'type' => ActionType::Scheduled,
        'status' => ActionStatus::Done,
        'config' => ['schedule' => ['at' => now()->toIso8601String()]],
    ]));

    $this->delete(stopScheduleUrl($this->task, $this->recurring))->assertRedirect();
    $this->delete(stopScheduleUrl($this->task, $oneOff))->assertRedirect();

    expect($this->recurring->fresh()->status)->toBe(ActionStatus::Running)
        ->and($oneOff->fresh()->status)->toBe(ActionStatus::Done)
        ->and(Activity::withoutGlobalScopes()->where('event', 'action.schedule_stopped')->count())->toBe(0);
});
