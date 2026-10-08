<?php

use App\Ai\Support\UsageMeter;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

function runWithTokens(Workspace $workspace, int $tokens, ?DateTimeInterface $at = null): void
{
    app(WorkspaceDiscoveryService::class)->runAs($workspace, function () use ($workspace, $tokens, $at): void {
        $project = Project::factory()->forWorkspace($workspace)->create();
        $action = TaskAction::factory()->forTask(Task::factory()->forProject($project)->create())->create();

        $action->runs()->create([
            'task_id' => $action->task_id, 'project_id' => $project->id, 'channel' => RunChannel::AppAi,
            'actor_type' => 'agent', 'status' => RunStatus::Succeeded, 'started_at' => $at ?? now(), 'usage' => ['total_tokens' => $tokens],
        ]);
    });
}

beforeEach(function () {
    $this->workspace = Workspace::factory()->ownedBy(User::factory()->create())->create();
});

test('sums this month of the workspace only', function () {
    runWithTokens($this->workspace, 300);
    runWithTokens($this->workspace, 200);
    runWithTokens($this->workspace, 999, now()->subMonthNoOverflow());
    runWithTokens(Workspace::factory()->ownedBy(User::factory()->create())->create(), 5000);

    expect(app(UsageMeter::class)->usedThisMonth($this->workspace))->toBe(500);
});

test('workspace budget overrides the config default', function () {
    config(['ai.monthly_token_budget' => 1000]);

    expect(app(UsageMeter::class)->budget($this->workspace))->toBe(1000);

    $this->workspace->update(['settings' => ['ai_budget' => 400]]);
    runWithTokens($this->workspace, 300);

    expect(app(UsageMeter::class)->remaining($this->workspace->fresh()))->toBe(100);
});
