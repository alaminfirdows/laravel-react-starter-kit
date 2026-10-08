<?php

use App\Domain\Catalog\Models\CatalogResource;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Skill;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Evidence;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Servers\FounderServer;
use App\Mcp\Tools\GetActionTool;
use App\Mcp\Tools\GetProjectContextTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Models\User;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->withMember($this->user, WorkspaceRole::Member)->create();
    $this->project = Project::factory()->forWorkspace($this->workspace)->create([
        'name' => 'Acme', 'one_liner' => 'Rockets for cats', 'primary_market' => 'DE', 'goals' => ['Launch in Q1'],
    ]);
    $this->task = Task::factory()->forProject($this->project)->create(['title' => 'Pricing', 'body_md' => 'Find a price.']);
    $this->task->forceFill(['completion_criteria' => [['key' => 'page', 'label' => 'Pricing page URL', 'kind' => 'evidence']]])->save();
    $this->action = TaskAction::factory()->forTask($this->task)->create(['title' => 'Draft tiers', 'instructions_md' => 'Three tiers.']);

    Passport::actingAs($this->user, ['mcp:use', 'workspace:'.$this->workspace->id]);
});

test('list_projects shows only projects of the token workspace', function () {
    $other = Workspace::factory()->withMember($this->user, WorkspaceRole::Owner)->create();
    Project::factory()->forWorkspace($other)->create(['name' => 'Elsewhere']);

    FounderServer::tool(ListProjectsTool::class)
        ->assertOk()
        ->assertSee(['Acme', $this->project->id])
        ->assertDontSee('Elsewhere');
});

test('get_project_context returns profile, market and goals, limited by sections', function () {
    FounderServer::tool(GetProjectContextTool::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertSee(['Rockets for cats', 'Primary market: DE', 'Launch in Q1', '## Progress']);

    FounderServer::tool(GetProjectContextTool::class, ['project_id' => $this->project->id, 'sections' => ['goals']])
        ->assertSee('Launch in Q1')
        ->assertDontSee('Rockets for cats');
});

test('list_tasks filters and paginates with a cursor', function () {
    Task::factory()->forProject($this->project)->count(3)->create();
    Task::factory()->forProject($this->project)->done()->create(['title' => 'Finished']);

    $first = FounderServer::tool(ListTasksTool::class, ['project_id' => $this->project->id, 'limit' => 2])
        ->assertOk()
        ->assertSee('next_cursor: ');

    FounderServer::tool(ListTasksTool::class, ['project_id' => $this->project->id, 'status' => TaskStatus::Done->value])
        ->assertSee('Finished')
        ->assertDontSee('Pricing')
        ->assertDontSee('next_cursor');

    FounderServer::tool(ListTasksTool::class, ['project_id' => $this->project->id, 'ready_only' => true])
        ->assertDontSee('Finished');
});

test('get_task returns criteria, actions, skills, resources and evidence', function () {
    $catalogTask = CatalogTask::factory()->create();
    $catalogTask->skills()->attach(Skill::factory()->create(['key' => 'pricing-coach']), ['required' => true]);
    $catalogTask->resources()->attach(CatalogResource::factory()->create(['title' => 'Pricing guide', 'url' => 'https://example.com/p']), ['sort_order' => 1, 'note' => 'Read first']);
    $this->task->forceFill(['catalog_task_id' => $catalogTask->id])->save();
    Evidence::factory()->forAction($this->action)->create(['kind' => EvidenceKind::Url, 'label' => 'Draft doc', 'value' => 'https://docs.example.com']);

    FounderServer::tool(GetTaskTool::class, ['task_id' => $this->task->id])
        ->assertOk()
        ->assertSee([
            '# Pricing', 'Find a price.', '`page` (evidence): Pricing page URL',
            "Draft tiers (`{$this->action->id}`", 'pricing-coach (required)',
            '[Pricing guide](https://example.com/p)', 'Read first', 'Draft doc — https://docs.example.com',
        ]);
});

test('get_action returns instructions and unmet criteria without the manual protocol', function () {
    FounderServer::tool(GetActionTool::class, ['action_id' => $this->action->id])
        ->assertOk()
        ->assertSee(['# Draft tiers', 'Three tiers.', 'Unmet criteria', 'Pricing page URL', 'Acme'])
        ->assertDontSee('Paste the result back');
});

test('ids from another workspace are not found, even when the user is a member there', function () {
    $foreign = Task::factory()->forProject(Project::factory()->forWorkspace(Workspace::factory()->create())->create())->create(['title' => 'Secret']);
    $mine = Workspace::factory()->withMember($this->user, WorkspaceRole::Owner)->create();
    $otherOwn = Task::factory()->forProject(Project::factory()->forWorkspace($mine)->create())->create(['title' => 'Other own']);
    $foreignAction = TaskAction::factory()->forTask($foreign)->create();

    foreach ([$foreign, $otherOwn] as $task) {
        FounderServer::tool(GetTaskTool::class, ['task_id' => $task->id])->assertHasErrors(['Task not found.']);
    }

    FounderServer::tool(GetActionTool::class, ['action_id' => $foreignAction->id])->assertHasErrors(['Action not found.']);
    FounderServer::tool(ListTasksTool::class, ['project_id' => $foreign->project_id])->assertHasErrors(['Project not found.']);
});
