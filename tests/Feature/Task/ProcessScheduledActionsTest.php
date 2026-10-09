<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\FakeDnsResolver;

beforeEach(function () {
    FakeDnsResolver::bind();
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

function cronAction(Task $task, array $schedule): TaskAction
{
    return TaskAction::factory()->forTask($task)->create([
        'type' => ActionType::Scheduled,
        'executor' => Executor::AppSystem,
        'config' => ['check' => 'https', 'schedule' => $schedule],
    ]);
}

test('a cron action runs at its first occurrence, once per window', function () {
    Http::fake(['https://acme.test/' => Http::response('', 200)]);
    $this->travelTo(now()->startOfDay()->addHours(8));
    $recurring = cronAction($this->task, ['cron' => '0 9 * * *', 'timezone' => config('app.timezone')]);

    processScheduled();
    expect($recurring->runs()->count())->toBe(0);

    Queue::fake();
    Log::spy();
    $this->travelTo(now()->startOfDay()->addHours(9)->addMinute());
    processScheduled();
    processScheduled();
    expect($recurring->runs()->count())->toBe(1);
    Log::shouldNotHaveReceived('info');
});

test('a cron action runs again at the next occurrence, even after completing', function () {
    Http::fake(['https://acme.test/' => Http::response('', 200)]);
    $this->travelTo(now()->startOfDay()->addHours(8));
    $recurring = cronAction($this->task, ['cron' => '0 9 * * *']);

    $this->travelTo(now()->startOfDay()->addHours(9)->addMinute());
    processScheduled();
    $recurring->refresh()->forceFill(['status' => ActionStatus::Done, 'completed_at' => now()])->save();

    $this->travel(1)->days();
    processScheduled();

    expect($recurring->runs()->count())->toBe(2);
});

test('several missed cron occurrences run once', function () {
    Http::fake(['https://acme.test/' => Http::response('', 200)]);
    $this->travelTo(now()->startOfDay()->addHours(8));
    $recurring = cronAction($this->task, ['cron' => '0 9 * * *']);

    $this->travel(5)->days();
    processScheduled();
    processScheduled();

    expect($recurring->runs()->count())->toBe(1);
});

test('an invalid cron expression is skipped without crashing', function () {
    $broken = cronAction($this->task, ['cron' => 'not a cron']);
    $this->travel(2)->days();

    processScheduled();

    expect($broken->runs()->count())->toBe(0);
});

test('a failed cron action is retried at the next occurrence', function () {
    Queue::fake();
    $this->travelTo(now()->startOfDay()->addHours(8));
    $recurring = cronAction($this->task, ['cron' => '0 9 * * *']);

    $this->travelTo(now()->startOfDay()->addHours(9)->addMinute());
    processScheduled();
    $recurring->refresh()->forceFill(['status' => ActionStatus::Failed])->save();

    processScheduled();
    expect($recurring->runs()->count())->toBe(1);

    $this->travel(1)->days();
    processScheduled();
    expect($recurring->runs()->count())->toBe(2);
});

test('a cron schedule honours its timezone', function () {
    Queue::fake();
    $this->travelTo(now('UTC')->startOfDay()->addHours(10));
    $recurring = cronAction($this->task, ['cron' => '0 9 * * *', 'timezone' => 'Asia/Dhaka']);

    // 09:00 Dhaka = 03:00 UTC next day boundary; at 10:00 UTC (16:00 Dhaka) the first occurrence is tomorrow 09:00 Dhaka.
    processScheduled();
    expect($recurring->runs()->count())->toBe(0);

    $this->travelTo(now('UTC')->startOfDay()->addDay()->addHours(2));
    processScheduled();
    expect($recurring->runs()->count())->toBe(0);

    $this->travelTo(now('UTC')->startOfDay()->addHours(3)->addMinute());
    processScheduled();
    expect($recurring->runs()->count())->toBe(1);
});

test('a start that throws leaves no reopen activity and does not crash', function () {
    Queue::fake();
    $this->travelTo(now()->startOfDay()->addHours(8));
    $recurring = cronAction($this->task, ['cron' => '0 9 * * *']);
    $recurring->forceFill(['status' => ActionStatus::Done, 'completed_at' => now()])->save();
    $this->task->forceFill(['status' => TaskStatus::Locked])->save();

    $this->travelTo(now()->startOfDay()->addHours(9)->addMinute());
    processScheduled();
    processScheduled();

    expect($recurring->runs()->count())->toBe(0)
        ->and($recurring->fresh()->status)->toBe(ActionStatus::Done)
        ->and(Activity::query()->where('event', 'action.reopened')->count())->toBe(0);
});
