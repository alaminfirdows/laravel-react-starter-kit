<?php

use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

if (! function_exists('currentWorkspace')) {
    /**
     * Workspace of the current request/job, or null when none is set.
     */
    function currentWorkspace(): ?Workspace
    {
        $context = app(WorkspaceDiscoveryService::class);

        return $context->isWorkspaceDiscovered() ? $context->currentWorkspace() : null;
    }
}

if (! function_exists('workspaceId')) {
    /**
     * ID of the current workspace, or null when none is set.
     */
    function workspaceId(): ?string
    {
        return currentWorkspace()?->id;
    }
}
