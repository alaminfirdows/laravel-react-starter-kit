<?php

namespace App\Mcp\Queries;

use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Data\McpConnectionData;
use App\Mcp\Support\McpActor;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Token;

/**
 * Not revoked, not expired tokens of a user, newest first.
 */
class ActiveMcpConnections
{
    /**
     * @return list<McpConnectionData>
     */
    public function handle(User $user): array
    {
        $tokens = $user->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->get();

        $workspaceIds = $tokens->map(fn (Token $token): ?string => $this->workspaceId($token))->filter()->unique()->values()->all();
        $workspaceNames = Workspace::query()->whereKey($workspaceIds)->pluck('name', 'id');

        return array_values(array_map(fn (Token $token): McpConnectionData => new McpConnectionData(
            id: (string) $token->getKey(),
            clientName: $token->client->name ?? McpActor::DEFAULT_CLIENT_NAME,
            workspaceName: $workspaceNames->get($this->workspaceId($token) ?? ''),
            createdAt: $token->created_at,
            expiresAt: $token->expires_at,
        ), $tokens->all()));
    }

    private function workspaceId(Token $token): ?string
    {
        foreach ($token->scopes ?? [] as $scope) {
            if (Str::startsWith($scope, McpActor::WORKSPACE_SCOPE_PREFIX)) {
                return Str::after($scope, McpActor::WORKSPACE_SCOPE_PREFIX);
            }
        }

        return null;
    }
}
