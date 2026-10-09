<?php

namespace App\Domain\Workspace\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Mcp\Actions\RevokeMcpConnection;
use App\Mcp\Queries\ActiveMcpConnections;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkspaceConnectionController extends Controller
{
    /**
     * Admins revoke a member's MCP connection to this workspace.
     */
    public function destroy(Request $request, Workspace $workspace, string $token, ActiveMcpConnections $connections, RevokeMcpConnection $revoke): RedirectResponse
    {
        $accessToken = $connections->workspaceTokens($workspace)->whereKey($token)->firstOrFail();
        $owner = User::query()->findOrFail($accessToken->user_id);

        Gate::authorize('revokeConnection', [$workspace, $owner]);

        $revoke->handle($accessToken, Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection revoked.')]);

        return back();
    }
}
