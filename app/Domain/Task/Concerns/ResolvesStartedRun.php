<?php

namespace App\Domain\Task\Concerns;

use App\Domain\Task\Actions\NotifyRunOutcome;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

/**
 * For queued run jobs: load a still-started run and its requesting user, work inside its workspace,
 * then tell the user how the run ended.
 */
trait ResolvesStartedRun
{
    /**
     * @param  callable(ActionRun, User): void  $callback
     */
    protected function withStartedRun(string $runId, string $userId, callable $callback): void
    {
        $run = ActionRun::query()->with('project')->find($runId);
        $user = User::query()->find($userId);

        if ($run === null || $user === null || $run->status !== RunStatus::Started) {
            return;
        }

        $workspace = Workspace::query()->findOrFail($run->project->workspace_id);

        app(WorkspaceDiscoveryService::class)->runAs($workspace, function () use ($callback, $run, $user): void {
            $callback($run, $user);
            app(NotifyRunOutcome::class)->handle($run->refresh(), $user);
        });
    }
}
