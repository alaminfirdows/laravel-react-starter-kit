<?php

namespace App\Mcp\Queries;

use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Data\McpConnectionData;
use App\Mcp\Support\McpActor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\Token;

/**
 * Not revoked, not expired tokens, newest first.
 */
class ActiveMcpConnections
{
    /**
     * Tokens of one user, any workspace.
     *
     * @return list<McpConnectionData>
     */
    public function handle(User $user): array
    {
        return $this->build($this->active()->where('user_id', $user->getKey()));
    }

    /**
     * Tokens of current members that are bound to the workspace.
     *
     * @return list<McpConnectionData>
     */
    public function forWorkspace(Workspace $workspace): array
    {
        return $this->build($this->workspaceTokens($workspace), withUserNames: true);
    }

    /**
     * Tokens bound to the workspace and no other: of current members, or of one user (member or not).
     *
     * @return Builder<Token>
     */
    public function workspaceTokens(Workspace $workspace, ?User $user = null): Builder
    {
        return $this->active()
            ->when($user, fn (Builder $query, User $user) => $query->where('user_id', $user->getKey()),
                fn (Builder $query) => $query->whereIn('user_id', $workspace->memberships()->select('user_id')))
            ->where('scopes', 'like', '%"'.McpActor::WORKSPACE_SCOPE_PREFIX.$workspace->id.'"%')
            ->where('scopes', 'not like', '%"'.McpActor::WORKSPACE_SCOPE_PREFIX.'%"'.McpActor::WORKSPACE_SCOPE_PREFIX.'%');
    }

    /**
     * The token's workspace, or null unless it has exactly one workspace scope.
     */
    public static function workspaceId(Token $token): ?string
    {
        $workspaceIds = McpActor::workspaceIds($token->scopes ?? []);

        return count($workspaceIds) === 1 ? $workspaceIds[0] : null;
    }

    /**
     * @return Builder<Token>
     */
    private function active(): Builder
    {
        return Token::query()
            ->with('client')
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest();
    }

    /**
     * Passport's Token::user() needs a loaded client, so names are looked up, not eager loaded.
     *
     * @param  Builder<Token>  $query
     * @return list<McpConnectionData>
     */
    private function build(Builder $query, bool $withUserNames = false): array
    {
        $tokens = $query->get();
        $userNames = $withUserNames ? User::query()->whereKey($tokens->pluck('user_id')->unique()->all())->pluck('name', 'id') : collect();

        $workspaceIds = $tokens->map(fn (Token $token): ?string => self::workspaceId($token))->filter()->unique()->values()->all();
        $workspaceNames = Workspace::query()->whereKey($workspaceIds)->pluck('name', 'id');

        return array_values(array_map(fn (Token $token): McpConnectionData => new McpConnectionData(
            id: (string) $token->getKey(),
            clientName: $token->client->name ?? McpActor::DEFAULT_CLIENT_NAME,
            workspaceName: $workspaceNames->get(self::workspaceId($token) ?? ''),
            createdAt: $token->created_at,
            expiresAt: $token->expires_at,
            userId: $token->user_id,
            userName: $userNames->get($token->user_id),
        ), $tokens->all()));
    }
}
