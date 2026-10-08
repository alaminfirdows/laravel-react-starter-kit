<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create(['website_url' => 'https://acme.test']);
    $this->task = Task::factory()->forProject($this->project)->create();
    $this->previous = TaskAction::factory()->forTask($this->task)->create(['sort_order' => 0]);
});

function waitAction(Task $task, array $wait): TaskAction
{
    return TaskAction::factory()->forTask($task)->create(['type' => ActionType::Wait, 'sort_order' => 1, 'config' => ['wait' => $wait]]);
}

function processScheduled(): void
{
    app(WorkspaceDiscoveryService::class)->forgetCurrentWorkspace();
    test()->artisan('actions:process-scheduled')->assertSuccessful();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace(test()->workspace);
}

test('a wait completes days after the previous step closed', function () {
    $wait = waitAction($this->task, ['days' => 3]);
    $this->previous->forceFill(['status' => ActionStatus::Done, 'completed_at' => now()->subDays(2)])->save();

    processScheduled();
    expect($wait->fresh()->status)->toBe(ActionStatus::Pending);

    $this->travel(1)->days();
    processScheduled();
    expect($wait->fresh()->status)->toBe(ActionStatus::Done);
});

test('a wait does not start before the previous required step is done', function () {
    $wait = waitAction($this->task, ['days' => 0]);

    processScheduled();

    expect($wait->fresh()->status)->toBe(ActionStatus::Pending);
});

test('a wait with a date completes once the date passed', function () {
    $wait = waitAction($this->task, ['until' => now()->addDay()->toDateString()]);

    processScheduled();
    expect($wait->fresh()->status)->toBe(ActionStatus::Pending);

    $this->travel(2)->days();
    processScheduled();
    expect($wait->fresh()->status)->toBe(ActionStatus::Done);
});

test('a due scheduled in-app action runs once', function () {
    Http::fake(['https://acme.test/' => Http::response('', 500)]);
    $scheduled = TaskAction::factory()->forTask($this->task)->create([
        'type' => ActionType::Scheduled,
        'executor' => Executor::AppSystem,
        'config' => ['check' => 'https', 'schedule' => ['at' => now()->subMinute()->toIso8601String()]],
    ]);

    processScheduled();
    processScheduled();

    expect($scheduled->runs()->sole()->channel)->toBe(RunChannel::System)
        ->and($scheduled->fresh()->status)->toBe(ActionStatus::Ready);
});

test('a scheduled action in the future or for the user does nothing', function () {
    $future = TaskAction::factory()->forTask($this->task)->create([
        'type' => ActionType::Scheduled, 'executor' => Executor::AppSystem,
        'config' => ['check' => 'https', 'schedule' => ['at' => now()->addDay()->toIso8601String()]],
    ]);
    $manual = TaskAction::factory()->forTask($this->task)->create([
        'type' => ActionType::Scheduled, 'executor' => Executor::User,
        'config' => ['schedule' => ['at' => now()->subDay()->toIso8601String()]],
    ]);

    processScheduled();

    expect($future->runs()->count() + $manual->runs()->count())->toBe(0);
});
