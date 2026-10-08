<?php

use App\Ai\Agents\DraftDocumentAgent;
use App\Ai\Exceptions\AiBudgetExceeded;
use App\Ai\Jobs\RunAppAiActionJob;
use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RunActionInApp;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();
    $this->task = Task::factory()->forProject($this->project)->create();
    $this->action = TaskAction::factory()->forTask($this->task)->appAi()->create(['status' => ActionStatus::Ready]);
});

function draftResponse(array $outputs = []): array
{
    return ['output_md' => '# ICP draft', 'outputs' => $outputs];
}

test('runs the agent, stores output and usage, then completes the action', function () {
    Embeddings::fake();
    DraftDocumentAgent::fake([draftResponse([
        ['kind' => 'document', 'label' => 'ICP', 'value' => '# ICP draft', 'doc_type' => 'icp'],
        ['kind' => 'url', 'label' => 'Source', 'value' => 'https://example.com'],
    ])]);

    $run = app(RunActionInApp::class)->handle($this->action, $this->user);

    expect($run->fresh())
        ->channel->toBe(RunChannel::AppAi)
        ->status->toBe(RunStatus::Succeeded)
        ->output_md->toBe('# ICP draft')
        ->usage->toHaveKeys(['provider', 'model', 'input_tokens', 'output_tokens', 'total_tokens'])
        ->and($run->fresh()->usage['model'])->toBe('claude-opus-5-5')
        ->and($run->fresh()->rendered_prompt)->not->toBeEmpty()
        ->and($this->action->fresh()->status)->toBe(ActionStatus::Done)
        ->and($this->action->evidence()->count())->toBe(1)
        ->and(KnowledgeDocument::query()->sole())
        ->doc_type->toBe(DocType::Icp)
        ->source->toBe(DocSource::AppAi);

    DraftDocumentAgent::assertPrompted(fn ($prompt) => $prompt->prompt === $run->rendered_prompt);
});

test('an action that needs approval waits for it instead of completing', function () {
    $this->action->forceFill(['requires_approval' => true])->save();
    DraftDocumentAgent::fake([draftResponse()]);

    $run = app(RunActionInApp::class)->handle($this->action, $this->user);

    expect($this->action->fresh()->status)->toBe(ActionStatus::AwaitingApproval)
        ->and($this->action->approvals()->sole())->status->toBe(ApprovalStatus::Pending)->summary_md->toBe('# ICP draft')
        ->and($run->fresh()->status)->toBe(RunStatus::Succeeded);
});

test('unmet criteria leave the action ready with the output saved', function () {
    $this->task->forceFill(['completion_criteria' => [['key' => 'live_url', 'label' => 'Live URL', 'kind' => 'evidence']]])->save();
    DraftDocumentAgent::fake([draftResponse()]);

    $run = app(RunActionInApp::class)->handle($this->action, $this->user);

    expect($run->fresh())->status->toBe(RunStatus::Succeeded)->output_md->toBe('# ICP draft')
        ->and($this->action->fresh()->status)->toBe(ActionStatus::Ready);
});

test('agent failure fails the run, returns the action to ready and allows a retry', function () {
    DraftDocumentAgent::fake(fn () => throw new RuntimeException('Provider timed out'));

    expect(fn () => app(RunActionInApp::class)->handle($this->action, $this->user))->toThrow(RuntimeException::class);

    $failed = $this->action->runs()->sole();
    expect($failed)->status->toBe(RunStatus::Failed)->error->toBe('Provider timed out')
        ->and($this->action->fresh()->status)->toBe(ActionStatus::Ready);

    DraftDocumentAgent::fake([draftResponse()]);
    app(RunActionInApp::class)->handle($this->action->fresh(), $this->user);

    expect($this->action->fresh()->status)->toBe(ActionStatus::Done);
});

test('a second click while running starts no second run', function () {
    Queue::fake();

    app(RunActionInApp::class)->handle($this->action, $this->user);

    expect(fn () => app(RunActionInApp::class)->handle($this->action->fresh(), $this->user))
        ->toThrow(InvalidActionTransition::class, 'already running');

    expect($this->action->runs()->count())->toBe(1);
    Queue::assertPushed(RunAppAiActionJob::class, 1);
});

test('refuses actions that are not app_ai', function () {
    $manual = TaskAction::factory()->forTask($this->task)->create();

    expect(fn () => app(RunActionInApp::class)->handle($manual, $this->user))
        ->toThrow(InvalidActionTransition::class);
});

test('a spent budget refuses before calling the provider', function () {
    $this->workspace->update(['settings' => ['ai_budget' => 100]]);
    $done = TaskAction::factory()->forTask($this->task)->create();
    $done->runs()->create([
        'task_id' => $this->task->id, 'project_id' => $this->project->id, 'channel' => RunChannel::AppAi,
        'actor_type' => 'agent', 'status' => RunStatus::Succeeded, 'started_at' => now(), 'usage' => ['total_tokens' => 150],
    ]);
    DraftDocumentAgent::fake();

    expect(fn () => app(RunActionInApp::class)->handle($this->action, $this->user))->toThrow(AiBudgetExceeded::class);

    DraftDocumentAgent::assertNeverPrompted();
    expect($this->action->runs()->count())->toBe(0);
});
