<?php

use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Exceptions\WorkspaceNotSetException;
use App\Domain\Workspace\Models\Workspace;
use Tests\Fixtures\TenantNote;

beforeEach(function () {
    TenantNote::createTable();

    $this->context = app(WorkspaceDiscoveryService::class);
    $this->first = Workspace::factory()->create();
    $this->second = Workspace::factory()->create();

    TenantNote::forceCreate(['workspace_id' => $this->first->id, 'body' => 'first']);
    TenantNote::forceCreate(['workspace_id' => $this->second->id, 'body' => 'second']);
});

test('queries fail closed without a workspace', function () {
    TenantNote::query()->get();
})->throws(WorkspaceNotSetException::class);

test('queries are limited to the current workspace', function () {
    $this->context->setCurrentWorkspace($this->first);

    expect(TenantNote::pluck('body')->all())->toBe(['first']);
});

test('new rows get the current workspace id', function () {
    $this->context->setCurrentWorkspace($this->second);

    $note = TenantNote::create(['body' => 'new']);

    expect($note->workspace_id)->toBe($this->second->id);
});

test('creating without a workspace fails', function () {
    TenantNote::create(['body' => 'orphan']);
})->throws(WorkspaceNotSetException::class);

test('scope can be bypassed or pinned to one workspace', function () {
    expect(TenantNote::withoutWorkspaceScope()->count())->toBe(2)
        ->and(TenantNote::forWorkspace($this->second)->pluck('body')->all())->toBe(['second']);
});

test('runAs switches and restores the context', function () {
    $this->context->setCurrentWorkspace($this->first);

    $bodies = $this->context->runAs($this->second, fn () => TenantNote::pluck('body')->all());

    expect($bodies)->toBe(['second'])
        ->and(workspaceId())->toBe($this->first->id);
});

test('helpers return null without a workspace', function () {
    expect(currentWorkspace())->toBeNull()
        ->and(workspaceId())->toBeNull();
});
