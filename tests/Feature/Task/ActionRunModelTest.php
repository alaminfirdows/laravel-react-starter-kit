<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\Evidence;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

beforeEach(function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $task = Task::factory()->forProject(Project::factory()->forWorkspace($workspace)->create())->create();
    $this->action = TaskAction::factory()->forTask($task)->create();
});

test('action has runs, last run, evidence and approvals', function () {
    $older = ActionRun::factory()->forAction($this->action)->create(['started_at' => now()->subHour()]);
    $run = ActionRun::factory()->forAction($this->action)->create();
    $this->action->forceFill(['last_run_id' => $run->id])->save();
    Evidence::factory()->forAction($this->action, 'live_url')->create();
    Approval::factory()->forAction($this->action)->create();

    $action = $this->action->fresh();

    expect($action->runs->pluck('id')->all())->toBe([$run->id, $older->id])
        ->and($action->lastRun->status)->toBe(RunStatus::Started)
        ->and($action->evidence->first()->criterion_key)->toBe('live_url')
        ->and($action->approvals->first()->subject->is($action))->toBeTrue()
        ->and($action->task->evidence)->toHaveCount(1);
});

test('check result counts only when it passed', function () {
    $failed = Evidence::factory()->forAction($this->action)->make(['kind' => EvidenceKind::CheckResult, 'passed' => false]);
    $passed = Evidence::factory()->forAction($this->action)->make(['kind' => EvidenceKind::CheckResult, 'passed' => true]);
    $url = Evidence::factory()->forAction($this->action)->make();

    expect($failed->satisfies())->toBeFalse()
        ->and($passed->satisfies())->toBeTrue()
        ->and($url->satisfies())->toBeTrue();
});
