<?php

use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Project\Models\Project;
use App\Domain\Prompt\Actions\RenderFullPrompt;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

beforeEach(function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create(['name' => 'Acme', 'one_liner' => 'Rockets for cats']);
    $this->task = Task::factory()->forProject($this->project)->create(['title' => 'Pricing']);
});

test('fills project, task and action placeholders and appends the protocol', function () {
    $template = PromptTemplate::factory()->create(['full_md' => 'Help {{ project.name }} ({{project.one_liner}}) with {{ task.title }}: {{ action.title }}. {{ project.unknown }}End']);
    $action = TaskAction::factory()->forTask($this->task)->create(['title' => 'Draft tiers', 'prompt_template_id' => $template->id]);

    $prompt = app(RenderFullPrompt::class)->handle($action);

    expect($prompt)->toStartWith('Help Acme (Rockets for cats) with Pricing: Draft tiers. End')
        ->and($prompt)->toContain(RenderFullPrompt::COMPLETION_PROTOCOL);
});

test('override wins over template; default used when neither exists', function () {
    $action = TaskAction::factory()->forTask($this->task)->create(['prompt_override_md' => 'Custom {{ task.title }}']);
    expect(app(RenderFullPrompt::class)->handle($action))->toStartWith('Custom Pricing');

    $plain = TaskAction::factory()->forTask($this->task)->create(['title' => 'Write it', 'instructions_md' => 'Do X']);
    expect(app(RenderFullPrompt::class)->handle($plain))->toContain('Pricing')->toContain('Do X')->toContain('Acme');
});

test('output is capped at 14000 characters', function () {
    $this->task->forceFill(['body_md' => str_repeat('a', 20000)])->save();
    $action = TaskAction::factory()->forTask($this->task)->create(['prompt_override_md' => '{{ task.body_md }}']);

    $prompt = app(RenderFullPrompt::class)->handle($action);

    expect(mb_strlen($prompt))->toBeLessThanOrEqual(RenderFullPrompt::MAX_CHARS)
        ->and($prompt)->toEndWith('…[truncated]');
});
