<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\AttachEvidence;
use App\Domain\Task\Actions\CompleteAction;
use App\Domain\Task\Actions\DecideApproval;
use App\Domain\Task\Actions\MarkTaskDone;
use App\Domain\Task\Actions\RequestApproval;
use App\Domain\Task\Actions\SkipAction;
use App\Domain\Task\Actions\StartAction;
use App\Domain\Task\Data\EvidenceData;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Exceptions\InvalidActionTransition;
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
    $this->founder = Actor::user($this->user);
    $this->agent = Actor::agent($this->user, 'Claude');

    $this->parent = Task::factory()->forProject($this->project)->create();
    $this->task = Task::factory()->childOf($this->parent)->create();
    Task::factory()->childOf($this->parent)->create();
    $this->first = TaskAction::factory()->forTask($this->task)->create(['sort_order' => 0]);
    $this->last = TaskAction::factory()->forTask($this->task)->create(['sort_order' => 1]);
});

function evidence(string $criterion, EvidenceKind $kind = EvidenceKind::Url, ?bool $passed = null): EvidenceData
{
    return new EvidenceData($kind, 'Proof', 'https://example.com', $criterion, passed: $passed);
}

test('start creates a run and moves the task in progress', function () {
    $run = app(StartAction::class)->handle($this->first, $this->agent, RunChannel::Mcp);

    expect($run->status)->toBe(RunStatus::Started)
        ->and($run->client_name)->toBe('Claude')
        ->and($this->first->fresh())->status->toBe(ActionStatus::Running)->last_run_id->toBe($run->id)
        ->and($this->task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($this->parent->fresh()->status)->toBe(TaskStatus::InProgress);
});

test('completing every required action closes the task and rolls up', function () {
    app(StartAction::class)->handle($this->first, $this->agent, RunChannel::Mcp);
    app(CompleteAction::class)->handle($this->first, $this->agent, 'Draft ready');

    expect($this->task->fresh()->progress_pct)->toBe(50)
        ->and($this->first->fresh()->lastRun)->status->toBe(RunStatus::Succeeded)->output_md->toBe('Draft ready');

    app(CompleteAction::class)->handle($this->last, $this->agent);

    expect($this->task->fresh())->status->toBe(TaskStatus::Done)->completed_by_type->toBe('agent')
        ->and($this->parent->fresh()->progress_pct)->toBe(50);
});

test('skipped required actions count as closed', function () {
    app(SkipAction::class)->handle($this->first, $this->founder, 'Not needed');
    app(CompleteAction::class)->handle($this->last, $this->founder);

    expect($this->task->fresh()->status)->toBe(TaskStatus::Done);
});

test('evidence criterion blocks the closing action until evidence exists', function () {
    $this->task->forceFill(['completion_criteria' => [['key' => 'live_url', 'label' => 'Live URL', 'kind' => 'evidence']]])->save();
    app(CompleteAction::class)->handle($this->first, $this->agent);   // not the closing action

    expect(fn () => app(CompleteAction::class)->handle($this->last->fresh(), $this->agent))
        ->toThrow(InvalidActionTransition::class, 'missing: Live URL');

    app(AttachEvidence::class)->handle($this->last, evidence('live_url'), $this->agent);
    app(CompleteAction::class)->handle($this->last->fresh(), $this->agent);

    expect($this->task->fresh()->verification)->toBe(Verification::EvidenceAttached);
});

test('criterion linked to an action key gates only that action', function () {
    $catalogAction = CatalogAction::factory()->create(['key' => 'publish']);
    $this->first->forceFill(['catalog_action_id' => $catalogAction->id])->save();
    $this->task->forceFill(['completion_criteria' => [['key' => 'https', 'label' => 'HTTPS works', 'kind' => 'check', 'action' => 'publish']]])->save();

    app(AttachEvidence::class)->handle($this->first, evidence('https', EvidenceKind::CheckResult, passed: false), $this->agent);

    expect(fn () => app(CompleteAction::class)->handle($this->first->fresh(), $this->agent))
        ->toThrow(InvalidActionTransition::class, 'HTTPS works');

    app(AttachEvidence::class)->handle($this->first, evidence('https', EvidenceKind::CheckResult, passed: true), $this->agent);

    expect(app(CompleteAction::class)->handle($this->first->fresh(), $this->agent)->status)->toBe(ActionStatus::Done);
});

test('evidence for an unknown criterion is refused', function () {
    app(AttachEvidence::class)->handle($this->first, evidence('nope'), $this->agent);
})->throws(InvalidActionTransition::class, 'no criterion [nope]');

test('approval gate: request, agent cannot decide, founder approves, then complete', function () {
    $this->last->forceFill(['requires_approval' => true])->save();
    app(CompleteAction::class)->handle($this->first, $this->agent);

    expect(fn () => app(CompleteAction::class)->handle($this->last->fresh(), $this->agent))
        ->toThrow(InvalidActionTransition::class, 'needs an approved approval');

    $approval = app(RequestApproval::class)->handle($this->last, $this->agent, 'Publish the landing page?');

    expect($this->last->fresh()->status)->toBe(ActionStatus::AwaitingApproval)
        ->and($this->task->fresh()->status)->toBe(TaskStatus::AwaitingApproval)
        ->and(app(RequestApproval::class)->handle($this->last->fresh(), $this->agent, 'again')->id)->toBe($approval->id)
        ->and(fn () => app(DecideApproval::class)->handle($approval, $this->agent, true))->toThrow(InvalidActionTransition::class, 'Agents cannot');

    app(DecideApproval::class)->handle($approval, $this->founder, true, 'Go');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($this->last->fresh()->status)->toBe(ActionStatus::Ready)
        ->and($this->task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and(fn () => app(DecideApproval::class)->handle($approval->fresh(), $this->founder, false))->toThrow(InvalidActionTransition::class, 'already approved');

    app(CompleteAction::class)->handle($this->last->fresh(), $this->agent);

    expect($this->task->fresh()->status)->toBe(TaskStatus::Done);
});

test('locked task refuses start', function () {
    $this->task->forceFill(['status' => TaskStatus::Locked])->save();

    app(StartAction::class)->handle($this->first->fresh(), $this->agent, RunChannel::Mcp);
})->throws(InvalidActionTransition::class, 'locked');

test('mark task done respects action criteria', function () {
    $this->task->forceFill(['completion_criteria' => [['key' => 'doc', 'label' => 'Doc link', 'kind' => 'evidence']]])->save();

    app(MarkTaskDone::class)->handle($this->task->fresh(), $this->founder);
})->throws(InvalidActionTransition::class, 'Doc link');
