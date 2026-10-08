<?php

use App\Domain\Project\Models\Project;
use App\Domain\Prompt\Actions\BuildDeepLink;
use App\Domain\Prompt\Enums\DeepLinkTarget;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

beforeEach(function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->task = Task::factory()->forProject(Project::factory()->forWorkspace($workspace)->create())->create();
});

test('chat link url-encodes the launcher', function () {
    $action = TaskAction::factory()->forTask($this->task)->create(['type' => ActionType::Ai, 'executor' => Executor::ClaudeDesktop]);

    $link = app(BuildDeepLink::class)->handle($action);

    expect($link->target)->toBe(DeepLinkTarget::Chat)
        ->and($link->url)->toStartWith('claude://claude.ai/new?q=')
        ->and($link->url)->not->toContain(' ', "\n", '·')
        ->and(rawurldecode(substr((string) $link->url, strlen('claude://claude.ai/new?q='))))->toBe($link->launcher);
});

test('file, browser and chrome actions target cowork', function (ActionType $type, Executor $executor) {
    $action = TaskAction::factory()->forTask($this->task)->create(['type' => $type, 'executor' => $executor]);

    expect(app(BuildDeepLink::class)->handle($action)->url)->toStartWith('claude://cowork/new?q=');
})->with([
    [ActionType::File, Executor::ClaudeDesktop],
    [ActionType::Browser, Executor::ClaudeDesktop],
    [ActionType::Research, Executor::ClaudeChrome],
]);

test('url over the cap is dropped so the UI falls back to copy', function () {
    $link = app(BuildDeepLink::class)->link(DeepLinkTarget::Chat, str_repeat('é', 2000));

    expect($link->url)->toBeNull()
        ->and($link->launcher)->toHaveLength(2000)
        ->and($link->toArray())->toMatchArray(['target' => 'chat', 'url' => null]);
});
