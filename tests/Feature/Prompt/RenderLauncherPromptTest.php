<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Skill;
use App\Domain\Project\Models\Project;
use App\Domain\Prompt\Actions\RenderLauncherPrompt;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

beforeEach(function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create(['name' => 'Acme Secret', 'one_liner' => 'Rockets for cats']);
});

test('launcher names IDs and skills but leaks no project data', function () {
    $catalogTask = CatalogTask::factory()->create();
    $catalogTask->skills()->attach(Skill::factory()->create(['key' => 'pricing-coach']), ['required' => true]);
    $task = Task::factory()->forProject($this->project)->create(['title' => 'Private pricing', 'body_md' => 'Margin 42%', 'catalog_task_id' => $catalogTask->id]);
    $action = TaskAction::factory()->forTask($task)->create(['title' => 'Draft tiers', 'instructions_md' => 'Use our churn data']);

    $launcher = app(RenderLauncherPrompt::class)->handle($action);

    expect($launcher)
        ->toContain($this->project->id, $task->id, $action->id)
        ->toContain('founder-os-task-runner, pricing-coach skills')
        ->toContain('get_task')
        ->not->toContain('Acme Secret')
        ->not->toContain('Rockets')
        ->not->toContain('Private pricing')
        ->not->toContain('Margin')
        ->not->toContain('Draft tiers')
        ->not->toContain('churn')
        ->and(substr_count($launcher, "\n"))->toBeLessThan(15);
});

test('launcher uses only the runner skill for custom tasks', function () {
    $task = Task::factory()->forProject($this->project)->create(['catalog_task_id' => null]);
    $action = TaskAction::factory()->forTask($task)->create();

    expect(app(RenderLauncherPrompt::class)->handle($action))->toContain('the founder-os-task-runner skill.');
});
