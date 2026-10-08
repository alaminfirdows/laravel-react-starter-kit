<?php

namespace App\Domain\Workspace\Contracts;

use App\Domain\Workspace\Exceptions\WorkspaceNotSetException;
use App\Domain\Workspace\Models\Workspace;

/**
 * Holds the workspace (tenant) for the current request, job or command.
 */
interface WorkspaceDiscoveryService
{
    public function isWorkspaceDiscovered(): bool;

    /**
     * @throws WorkspaceNotSetException
     */
    public function currentWorkspace(): Workspace;

    public function setCurrentWorkspace(string|Workspace $workspace): void;

    public function forgetCurrentWorkspace(): void;

    /**
     * @throws WorkspaceNotSetException
     */
    public function workspaceId(): string;

    /**
     * Run the callback with the given workspace as context, then restore
     * the previous context.
     *
     * @template TReturn
     *
     * @param  callable(Workspace): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Workspace $workspace, callable $callback): mixed;
}
