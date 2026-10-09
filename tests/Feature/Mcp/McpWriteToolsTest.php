<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Events\RunFinished;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Servers\FounderServer;
use App\Mcp\Tools\AttachEvidenceTool;
use App\Mcp\Tools\CompleteActionTool;
use App\Mcp\Tools\RequestApprovalTool;
use App\Mcp\Tools\SaveOutputTool;
use App\Mcp\Tools\StartActionTool;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->withMember($this->user, WorkspaceRole::Member)->create();
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();
    $this->task = Task::factory()->forProject($this->project)->create(['title' => 'Pricing']);
    $this->task->forceFill(['completion_criteria' => [['key' => 'page', 'label' => 'Pricing page URL', 'kind' => 'evidence']]])->save();
    $this->action = TaskAction::factory()->forTask($this->task)->create(['title' => 'Draft tiers']);

    Passport::actingAs($this->user, ['mcp:use', 'workspace:'.$this->workspace->id]);
});

test('start, save output, attach evidence and complete an action', function () {
    FounderServer::tool(StartActionTool::class, ['action_id' => $this->action->id])->assertOk()->assertSee('run_id: ');

    $run = $this->action->refresh()->lastRun;
    expect($run->channel)->toBe(RunChannel::Mcp)
        ->and($run->client_name)->not->toBeNull()
        ->and($this->action->status)->toBe(ActionStatus::Running);

    FounderServer::tool(SaveOutputTool::class, ['action_id' => $this->action->id, 'output_md' => 'Draft v1'])->assertOk();
    expect($run->refresh()->output_md)->toBe('Draft v1');

    FounderServer::tool(CompleteActionTool::class, [
        'action_id' => $this->action->id,
        'run_id' => $run->id,
        'output_md' => 'Final tiers',
        'evidence' => [['kind' => 'url', 'label' => 'Pricing page', 'value' => 'https://acme.test/pricing', 'criterion_key' => 'page']],
    ])->assertOk()->assertSee('is done');

    expect($this->action->refresh()->status)->toBe(ActionStatus::Done)
        ->and($run->refresh()->status)->toBe(RunStatus::Succeeded)
        ->and($run->output_md)->toBe('Final tiers')
        ->and($this->action->evidence()->count())->toBe(1);

    $this->assertDatabaseHas('activity_log', ['event' => 'action.completed', 'client_name' => $run->client_name]);
});

test('complete_action closes the started run and broadcasts it once', function () {
    Event::fake([RunFinished::class]);
    $run = ActionRun::factory()->forAction($this->action)->create(['status' => RunStatus::Started]);
    $this->action->forceFill(['status' => ActionStatus::Running, 'last_run_id' => $run->id])->save();

    FounderServer::tool(CompleteActionTool::class, [
        'action_id' => $this->action->id,
        'evidence' => [['kind' => 'url', 'label' => 'Pricing page', 'value' => 'https://acme.test/pricing', 'criterion_key' => 'page']],
    ])->assertOk();

    expect($run->refresh()->status)->toBe(RunStatus::Succeeded);
    Event::assertDispatchedTimes(RunFinished::class, 1);
    Event::assertDispatched(RunFinished::class, fn (RunFinished $event): bool => $event->run->is($run));
    $this->assertDatabaseHas('activity_log', ['event' => 'run.finished', 'subject_id' => $this->action->id]);
});

test('failed complete_action keeps no evidence', function () {
    $this->action->forceFill(['requires_approval' => true])->save();

    FounderServer::tool(CompleteActionTool::class, [
        'action_id' => $this->action->id,
        'evidence' => [['kind' => 'url', 'label' => 'Pricing page', 'value' => 'https://acme.test/pricing', 'criterion_key' => 'page']],
    ])->assertHasErrors();

    expect($this->action->evidence()->count())->toBe(0)
        ->and($this->action->refresh()->status)->toBe(ActionStatus::Pending);
});

test('complete_action names the missing criterion', function () {
    FounderServer::tool(CompleteActionTool::class, ['action_id' => $this->action->id])
        ->assertHasErrors(['Pricing page URL']);

    expect($this->action->refresh()->status)->not->toBe(ActionStatus::Done);
});

test('attach_evidence refuses check results from agents', function () {
    FounderServer::tool(AttachEvidenceTool::class, [
        'action_id' => $this->action->id, 'kind' => 'check_result', 'label' => 'HTTPS', 'value' => 'passed',
    ])->assertHasErrors();

    FounderServer::tool(AttachEvidenceTool::class, [
        'action_id' => $this->action->id, 'kind' => 'note', 'label' => 'Reasoning', 'value' => 'Anchored on rivals.',
    ])->assertOk();

    expect($this->action->evidence()->count())->toBe(1);
});

test('attach_evidence refuses non-web url schemes', function (string $value) {
    FounderServer::tool(AttachEvidenceTool::class, [
        'action_id' => $this->action->id, 'kind' => 'url', 'label' => 'Page', 'value' => $value,
    ])->assertHasErrors();

    expect($this->action->evidence()->count())->toBe(0);
})->with([
    'javascript' => ['javascript:alert(1)'],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'ftp' => ['ftp://acme.test/file'],
]);

test('save_output needs a started run', function () {
    FounderServer::tool(SaveOutputTool::class, ['action_id' => $this->action->id, 'output_md' => 'x'])
        ->assertHasErrors(['start_action']);
});

test('request_approval creates a pending approval; agents get no approve tool', function () {
    FounderServer::tool(RequestApprovalTool::class, ['action_id' => $this->action->id, 'summary_md' => 'Please check tiers'])
        ->assertOk();

    expect($this->action->approvals()->sole()->status)->toBe(ApprovalStatus::Pending)
        ->and(collect((new ReflectionClass(FounderServer::class))->getDefaultProperties()['tools'])
            ->filter(fn (string $tool): bool => str_contains(strtolower($tool), 'approve')))->toBeEmpty();
});

test('viewer cannot change actions', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer]);
    Passport::actingAs($viewer, ['mcp:use', 'workspace:'.$this->workspace->id]);

    FounderServer::tool(StartActionTool::class, ['action_id' => $this->action->id])
        ->assertHasErrors(['permission']);

    expect($this->action->refresh()->last_run_id)->toBeNull();
});

test('actions of another workspace are not found', function () {
    $foreign = TaskAction::factory()->forTask(Task::factory()->forProject(Project::factory()->create())->create())->create();

    FounderServer::tool(StartActionTool::class, ['action_id' => $foreign->id])
        ->assertHasErrors(['Action not found.']);
});
