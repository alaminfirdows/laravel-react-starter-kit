<?php

namespace App\Ai\Support;

use App\Ai\Exceptions\AiBudgetExceeded;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Monthly token use per workspace, from `action_runs.usage.total_tokens`.
 * Budget: `workspaces.settings.ai_budget`, else `ai.monthly_token_budget`.
 */
class UsageMeter
{
    public function budget(Workspace $workspace): int
    {
        return (int) ($workspace->settings['ai_budget'] ?? config('ai.monthly_token_budget'));
    }

    public function usedThisMonth(Workspace $workspace): int
    {
        return (int) ActionRun::query()
            ->whereIn('project_id', Project::withoutWorkspaceScope()->where('workspace_id', $workspace->id)->select('id'))
            ->where('started_at', '>=', now()->startOfMonth())
            ->whereNotNull('usage')
            ->sum(DB::raw("(usage->>'total_tokens')::bigint"));
    }

    public function remaining(Workspace $workspace): int
    {
        return max(0, $this->budget($workspace) - $this->usedThisMonth($workspace));
    }

    /**
     * @throws AiBudgetExceeded
     */
    public function ensureWithinBudget(Workspace $workspace): void
    {
        if ($this->remaining($workspace) === 0) {
            throw AiBudgetExceeded::for($workspace, $this->budget($workspace));
        }
    }
}
