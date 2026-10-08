<?php

namespace App\Domain\Workspace\Services;

use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Exceptions\WorkspaceNotSetException;
use App\Domain\Workspace\Models\Workspace;

/**
 * Bound as a scoped instance, so the context resets for every request
 * (Octane) and every queued job.
 */
class WorkspaceContext implements WorkspaceDiscoveryService
{
    protected ?Workspace $workspace = null;

    public function isWorkspaceDiscovered(): bool
    {
        return $this->workspace !== null;
    }

    public function currentWorkspace(): Workspace
    {
        return $this->workspace ?? throw WorkspaceNotSetException::make();
    }

    public function setCurrentWorkspace(string|Workspace $workspace): void
    {
        $this->workspace = $workspace instanceof Workspace
            ? $workspace
            : Workspace::query()->findOrFail($workspace);
    }

    public function forgetCurrentWorkspace(): void
    {
        $this->workspace = null;
    }

    public function workspaceId(): string
    {
        return $this->currentWorkspace()->id;
    }

    public function runAs(Workspace $workspace, callable $callback): mixed
    {
        $previous = $this->workspace;

        $this->workspace = $workspace;

        try {
            return $callback($workspace);
        } finally {
            $this->workspace = $previous;
        }
    }
}
