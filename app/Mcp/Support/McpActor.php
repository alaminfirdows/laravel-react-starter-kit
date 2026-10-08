<?php

namespace App\Mcp\Support;

use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

/**
 * Who is calling an MCP tool: the token's user, the OAuth client (e.g. "Claude"),
 * and the workspace the call runs in. Resolving it also sets the current workspace,
 * so tenant models are scoped the same way as in the web app.
 */
final readonly class McpActor
{
    public const string WORKSPACE_SCOPE_PREFIX = 'workspace:';

    public const string DEFAULT_CLIENT_NAME = 'MCP client';

    public function __construct(
        public User $user,
        public Workspace $workspace,
        public WorkspaceRole $role,
        public string $clientName,
    ) {}

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public static function from(Request $request): self
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $token = $user->token();
        $workspace = self::workspaceFromToken($token) ?? $user->resolveCurrentWorkspace();
        $role = $workspace ? $user->workspaceRole($workspace) : null;

        if ($workspace === null || $role === null || ! $workspace->isActive()) {
            throw new AuthorizationException('This token has no access to a workspace.');
        }

        app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);

        return new self($user, $workspace, $role, self::clientName($token));
    }

    public function actor(): Actor
    {
        return Actor::agent($this->user, $this->clientName);
    }

    private static function workspaceFromToken(mixed $token): ?Workspace
    {
        if (! $token instanceof AccessToken) {
            return null;
        }

        /** @var list<string> $scopes */
        $scopes = $token->oauth_scopes ?? [];

        foreach ($scopes as $scope) {
            if (Str::startsWith($scope, self::WORKSPACE_SCOPE_PREFIX)) {
                return Workspace::query()->whereKey(Str::after($scope, self::WORKSPACE_SCOPE_PREFIX))->first()
                    ?? throw new AuthorizationException('Unknown workspace in token scope.');
            }
        }

        return null;
    }

    private static function clientName(mixed $token): string
    {
        $clientId = $token instanceof AccessToken ? $token->oauth_client_id : null;

        if ($clientId === null) {
            return self::DEFAULT_CLIENT_NAME;
        }

        $name = Passport::client()->newQuery()->whereKey($clientId)->value('name');

        return is_string($name) && $name !== '' ? $name : self::DEFAULT_CLIENT_NAME;
    }
}
