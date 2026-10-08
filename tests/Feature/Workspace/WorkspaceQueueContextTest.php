<?php

use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\RecordWorkspaceJob;

beforeEach(function () {
    RecordWorkspaceJob::$seen = [];
    $this->context = app(WorkspaceDiscoveryService::class);
});

test('sync jobs see the dispatching workspace and the context is kept', function () {
    $workspace = Workspace::factory()->create();
    $this->context->setCurrentWorkspace($workspace);

    RecordWorkspaceJob::dispatch();

    expect(RecordWorkspaceJob::$seen)->toBe([$workspace->id])
        ->and(workspaceId())->toBe($workspace->id);
});

test('sync jobs restore the outer context after running as another workspace', function () {
    $first = Workspace::factory()->create();
    $second = Workspace::factory()->create();
    $this->context->setCurrentWorkspace($first);

    $this->context->runAs($second, function (): void {
        RecordWorkspaceJob::dispatch();
    });

    expect(RecordWorkspaceJob::$seen)->toBe([$second->id])
        ->and(workspaceId())->toBe($first->id);
});

test('jobs without a workspace run without one', function () {
    RecordWorkspaceJob::dispatch();

    expect(RecordWorkspaceJob::$seen)->toBe([null])
        ->and(workspaceId())->toBeNull();
});

test('database queue carries the workspace id to the worker', function () {
    config(['queue.default' => 'database']);

    $workspace = Workspace::factory()->create();
    $this->context->setCurrentWorkspace($workspace);

    RecordWorkspaceJob::dispatch();

    $payload = json_decode(DB::table('jobs')->value('payload'), true);
    expect($payload['workspace_id'])->toBe($workspace->id);

    $this->context->forgetCurrentWorkspace();

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    expect(RecordWorkspaceJob::$seen)->toBe([$workspace->id])
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(workspaceId())->toBeNull();
});
