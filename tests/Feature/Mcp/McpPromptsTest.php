<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Prompts\WorkOnTaskPrompt;
use App\Mcp\Resources\ProjectContextResource;
use App\Mcp\Resources\TaskResource;
use App\Mcp\Servers\FounderServer;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->withMember($this->user, WorkspaceRole::Member)->create();
    $this->project = Project::factory()->forWorkspace($this->workspace)->create(['name' => 'Acme', 'one_liner' => 'Rockets for cats']);
    $this->task = Task::factory()->forProject($this->project)->create(['title' => 'Pricing']);

    Passport::actingAs($this->user, ['mcp:use', 'workspace:'.$this->workspace->id]);
});

test('run-task returns the launcher for the next open action', function () {
    TaskAction::factory()->forTask($this->task)->create(['title' => 'Research', 'sort_order' => 1, 'status' => ActionStatus::Done]);
    $next = TaskAction::factory()->forTask($this->task)->create(['title' => 'Draft tiers', 'sort_order' => 2]);

    FounderServer::prompt(WorkOnTaskPrompt::class, ['task_id' => $this->task->id])
        ->assertOk()
        ->assertSee(['Founder OS connector', 'Action: '.$next->id])
        ->assertDontSee('Rockets for cats');
});

test('run-task fails when the task has no open action', function () {
    FounderServer::prompt(WorkOnTaskPrompt::class, ['task_id' => $this->task->id])
        ->assertHasErrors(['no open actions']);
});

test('project context and task resources render markdown', function () {
    FounderServer::resource(ProjectContextResource::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertSee(['Acme', 'Rockets for cats']);

    FounderServer::resource(TaskResource::class, ['task_id' => $this->task->id])
        ->assertOk()
        ->assertSee('Pricing');
});

test('resources of another workspace are not found', function () {
    $foreign = Project::factory()->create(['name' => 'Elsewhere']);

    FounderServer::resource(ProjectContextResource::class, ['project_id' => $foreign->id])
        ->assertHasErrors(['Project not found.']);
});
