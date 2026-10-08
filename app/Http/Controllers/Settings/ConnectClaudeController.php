<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mcp\Data\McpConnectionData;
use App\Mcp\Queries\ActiveMcpConnections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConnectClaudeController extends Controller
{
    /**
     * Show the connector URL, setup steps and the user's active connections.
     */
    public function edit(Request $request, ActiveMcpConnections $connections): Response
    {
        return Inertia::render('settings/connect-claude', [
            'connectorUrl' => route('mcp.founder'),
            'connections' => fn (): array => array_map(
                fn (McpConnectionData $connection): array => $connection->toArray(),
                $connections->handle($request->user()),
            ),
        ]);
    }

    /**
     * Revoke one connection (access and refresh token).
     */
    public function destroy(Request $request, string $token): RedirectResponse
    {
        $accessToken = $request->user()->tokens()->whereKey($token)->firstOrFail();

        $accessToken->revoke();
        $accessToken->refreshToken?->revoke();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection revoked.')]);

        return back();
    }
}
