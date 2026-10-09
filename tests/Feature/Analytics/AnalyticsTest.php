<?php

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Models\Activity;
use App\Domain\Analytics\Queries\DropOffByTask;
use App\Domain\Analytics\Queries\TaskCompletionStats;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->catalogTask = CatalogTask::factory()->create(['key' => 'pricing', 'title' => 'Pricing page']);
});

/**
 * One workspace with a project task based on the shared catalog task.
 */
function analyticsTask(CatalogTask $catalogTask, string $slug, string $title = 'Our secret pricing'): Task
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->ownedBy($user)->create(['slug' => $slug]);
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $project = Project::factory()->forWorkspace($workspace)->create();

    return Task::factory()->forProject($project)->create([
        'catalog_task_id' => $catalogTask->id,
        'title' => $title,
        'status' => TaskStatus::InProgress,
    ]);
}

function recordTaskEvent(Task $task, string $event): void
{
    app(ActivityRecorder::class)->record($event, $task, [], Actor::system());
}

test('completion stats sum all workspaces and expose only catalog data', function () {
    $done = analyticsTask($this->catalogTask, 'acme');
    recordTaskEvent($done, 'task.status_changed');
    $done->forceFill(['status' => TaskStatus::Done, 'completed_at' => $done->created_at->addHours(4)])->save();
    recordTaskEvent($done, 'task.completed');

    recordTaskEvent(analyticsTask($this->catalogTask, 'beta'), 'action.started');
    recordTaskEvent(analyticsTask($this->catalogTask, 'gamma'), 'comment.posted');

    $stats = app(TaskCompletionStats::class)->handle(now()->subDays(90));

    expect($stats)->toHaveCount(1)
        ->and($stats[0]->catalogTaskKey)->toBe('pricing')
        ->and($stats[0]->title)->toBe('Pricing page')
        ->and($stats[0]->projects)->toBe(2)
        ->and($stats[0]->started)->toBe(2)
        ->and($stats[0]->completed)->toBe(1)
        ->and($stats[0]->completionRate())->toBe(0.5)
        ->and($stats[0]->avgHoursToComplete)->toBe(4.0);
});

test('completion stats count action starts and tasks closed by rollup', function () {
    $started = analyticsTask($this->catalogTask, 'acme');
    app(ActivityRecorder::class)->record('action.started', TaskAction::factory()->forTask($started)->create(), [], Actor::system());

    $rolledUp = analyticsTask($this->catalogTask, 'beta');
    $rolledUp->forceFill(['status' => TaskStatus::Done, 'completed_at' => $rolledUp->created_at->addHours(2)])->save();
    recordTaskEvent($rolledUp, 'task.status_changed');

    $stats = app(TaskCompletionStats::class)->handle(now()->subDays(90));

    expect($stats)->toHaveCount(1)
        ->and($stats[0]->started)->toBe(2)
        ->and($stats[0]->completed)->toBe(1)
        ->and($stats[0]->avgHoursToComplete)->toBe(2.0);
});

test('events outside the window and custom tasks are ignored', function () {
    $old = analyticsTask($this->catalogTask, 'acme');
    recordTaskEvent($old, 'task.status_changed');
    Activity::withoutWorkspaceScope()->where('subject_id', $old->id)->update(['created_at' => now()->subDays(120)]);

    $custom = analyticsTask($this->catalogTask, 'beta');
    $custom->forceFill(['catalog_task_id' => null])->save();
    recordTaskEvent($custom, 'task.status_changed');

    expect(app(TaskCompletionStats::class)->handle(now()->subDays(90)))->toBe([]);
});

test('drop off counts open tasks without recent change', function () {
    $stalled = analyticsTask($this->catalogTask, 'acme');
    recordTaskEvent($stalled, 'task.status_changed');
    $stalled->newQueryWithoutScopes()->whereKey($stalled->id)->toBase()->update(['updated_at' => now()->subDays(20)]);

    recordTaskEvent(analyticsTask($this->catalogTask, 'beta'), 'task.status_changed');

    $dropOff = app(DropOffByTask::class)->handle(now()->subDays(90));

    expect($dropOff)->toHaveCount(1)
        ->and($dropOff[0]->catalogTaskKey)->toBe('pricing')
        ->and($dropOff[0]->started)->toBe(2)
        ->and($dropOff[0]->stalled)->toBe(1)
        ->and($dropOff[0]->dropOffRate())->toBe(0.5);
});

test('admin dashboard defers aggregated analytics without workspace data', function () {
    recordTaskEvent(analyticsTask($this->catalogTask, 'acme'), 'task.status_changed');

    $this->actingAs(User::factory()->admin()->create())->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->missing('completionStats')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('completionStats', 1)
                ->has('completionStats.0', fn (Assert $stat) => $stat
                    ->where('key', 'pricing')
                    ->where('title', 'Pricing page')
                    ->where('projects', 1)
                    ->where('started', 1)
                    ->where('completed', 0)
                    ->where('completionRate', 0)
                    ->where('avgHoursToComplete', null)
                )
                ->has('dropOff', 0)
            )
        );
});

test('non admin cannot see analytics', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});
