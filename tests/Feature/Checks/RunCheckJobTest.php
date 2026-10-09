<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RunActionInApp;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\FakeDnsResolver;

beforeEach(function () {
    FakeDnsResolver::bind();
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $workspace = Workspace::factory()->ownedBy($this->user)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create(['website_url' => 'https://acme.test']);
    $this->task = Task::factory()->forProject($this->project)->create();
    $this->task->forceFill(['completion_criteria' => [['key' => 'site_secure', 'label' => 'HTTPS works', 'kind' => 'check', 'check_ref' => 'https']]])->save();
    $this->action = TaskAction::factory()->forTask($this->task)->create([
        'type' => ActionType::Check,
        'executor' => Executor::AppSystem,
        'status' => ActionStatus::Ready,
        'config' => ['check' => 'https'],
    ]);
});

test('a passing check proves the criterion and completes the action', function () {
    Http::fake(['https://acme.test/' => Http::response('ok')]);

    $run = app(RunActionInApp::class)->handle($this->action, $this->user);

    expect($run->fresh())->channel->toBe(RunChannel::System)->status->toBe(RunStatus::Succeeded)
        ->and($this->action->evidence()->sole())
        ->kind->toBe(EvidenceKind::CheckResult)
        ->criterion_key->toBe('site_secure')
        ->passed->toBeTrue()
        ->and($this->action->fresh()->status)->toBe(ActionStatus::Done);
});

test('an unreachable domain fails the run with the reason, no crash', function () {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    $run = app(RunActionInApp::class)->handle($this->action, $this->user);

    expect($run->fresh())->status->toBe(RunStatus::Failed)->error->toContain('Could not reach')
        ->and($this->action->evidence()->sole()->passed)->toBeFalse()
        ->and($this->action->fresh()->status)->toBe(ActionStatus::Ready);
});

test('action config url wins over the project website; no url fails cleanly', function () {
    Http::fake(['https://landing.acme.test/' => Http::response('ok')]);
    $this->action->forceFill(['config' => ['check' => 'https', 'url' => 'landing.acme.test']])->save();

    app(RunActionInApp::class)->handle($this->action, $this->user);
    expect($this->action->fresh()->status)->toBe(ActionStatus::Done);

    $this->project->update(['website_url' => null]);
    $other = TaskAction::factory()->forTask($this->task)->create(['executor' => Executor::AppSystem, 'status' => ActionStatus::Ready, 'config' => ['check' => 'sitemap']]);

    expect(app(RunActionInApp::class)->handle($other, $this->user)->fresh())
        ->status->toBe(RunStatus::Failed)->error->toBe('Add the project website first.');
});

test('an unknown check key fails the run', function () {
    $this->action->forceFill(['config' => ['check' => 'nope']])->save();

    expect(app(RunActionInApp::class)->handle($this->action, $this->user)->fresh())
        ->status->toBe(RunStatus::Failed)->error->toContain('Unknown check');
});
