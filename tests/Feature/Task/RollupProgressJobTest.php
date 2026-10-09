<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\MarkTaskDone;
use App\Domain\Task\Jobs\RollupProgressJob;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create();
    $root = Task::factory()->forProject($this->project)->create();
    $this->leaves = Task::factory()->count(3)->childOf($root)->create();
});

test('task changes dispatch one delayed rollup per project', function () {
    Queue::fake();

    app(MarkTaskDone::class)->handle($this->leaves[0], Actor::user($this->user));
    app(MarkTaskDone::class)->handle($this->leaves[1], Actor::user($this->user));

    Queue::assertPushed(RollupProgressJob::class, fn (RollupProgressJob $job): bool => $job->projectId === $this->project->id && $job->delay !== null);
    expect(Queue::pushed(RollupProgressJob::class))->toHaveCount(1);
});

test('job caches mean leaf progress without a workspace context', function () {
    $this->leaves[0]->forceFill(['progress_pct' => 100])->save();
    $this->leaves[1]->forceFill(['progress_pct' => 50])->save();
    app(WorkspaceDiscoveryService::class)->forgetCurrentWorkspace();

    (new RollupProgressJob($this->project->id))->handle();

    expect(Project::withoutWorkspaceScope()->find($this->project->id)->progress_pct)->toBe(50);
});

test('rollup runs only after the transaction commits', function () {
    $ran = 0;
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$ran): void {
        if ($event->job->resolveName() === RollupProgressJob::class) {
            $ran++;
        }
    });

    DB::transaction(function () use (&$ran): void {
        RollupProgressJob::debounce($this->project->id);

        expect($ran)->toBe(0);
    });

    expect($ran)->toBe(1);
});

test('rolled back changes dispatch no rollup', function () {
    $ran = 0;
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$ran): void {
        if ($event->job->resolveName() === RollupProgressJob::class) {
            $ran++;
        }
    });

    rescue(fn () => DB::transaction(function (): void {
        RollupProgressJob::debounce($this->project->id);

        throw new RuntimeException('rollback');
    }), report: false);

    expect($ran)->toBe(0);

    RollupProgressJob::debounce($this->project->id);

    expect($ran)->toBe(1);
});
