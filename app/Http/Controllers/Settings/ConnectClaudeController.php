<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Activity\Data\Actor;
use App\Http\Controllers\Controller;
use App\Mcp\Actions\RevokeMcpConnection;
use App\Mcp\Data\McpConnectionData;
use App\Mcp\Queries\ActiveMcpConnections;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            'team' => fn (): ?array => $this->team($request->user(), $connections),
        ]);
    }

    /**
     * Revoke one connection (access and refresh token).
     */
    public function destroy(Request $request, string $token, RevokeMcpConnection $revoke): RedirectResponse
    {
        $accessToken = $request->user()->tokens()->whereKey($token)->firstOrFail();

        $revoke->handle($accessToken, Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection revoked.')]);

        return back();
    }

    /**
     * Members' connections to the current workspace, for those who may remove members.
     *
     * @return array{workspace: array{slug: string, name: string}, connections: list<array<string, mixed>>}|null
     */
    private function team(User $user, ActiveMcpConnections $connections): ?array
    {
        $workspace = $user->resolveCurrentWorkspace();

        if ($workspace === null || ! $user->can('removeAnyMember', $workspace)) {
            return null;
        }

        $members = $workspace->members()->get()->keyBy('id');

        return [
            'workspace' => ['slug' => $workspace->slug, 'name' => $workspace->name],
            'connections' => array_map(fn (McpConnectionData $connection): array => [
                ...$connection->toArray(),
                'canRevoke' => Gate::forUser($user)->allows('revokeConnection', [$workspace, $members->get($connection->userId)]),
            ], $connections->forWorkspace($workspace)),
        ];
    }
}
