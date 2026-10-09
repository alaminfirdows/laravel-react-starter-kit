<?php

namespace App\Mcp\Support;

use Illuminate\Support\Str;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\ScopeRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Passport scopes plus the "workspace:<ulid>" pattern that binds an MCP token to one workspace.
 * The consent screen adds that scope after a membership check; it is never shown as a choice to the client.
 */
class McpScopeRepository extends ScopeRepository
{
    public static function isWorkspaceScope(string $identifier): bool
    {
        return Str::startsWith($identifier, McpActor::WORKSPACE_SCOPE_PREFIX)
            && Str::isUlid(Str::after($identifier, McpActor::WORKSPACE_SCOPE_PREFIX));
    }

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return self::isWorkspaceScope($identifier)
            ? new Scope(McpActor::WORKSPACE_SCOPE_PREFIX.Str::after($identifier, McpActor::WORKSPACE_SCOPE_PREFIX))
            : parent::getScopeEntityByIdentifier($identifier);
    }

    /**
     * @param  ScopeEntityInterface[]  $scopes
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        [$workspaceScopes, $otherScopes] = collect($scopes)->partition(
            fn (ScopeEntityInterface $scope): bool => self::isWorkspaceScope($scope->getIdentifier()),
        );

        return [
            ...parent::finalizeScopes($otherScopes->values()->all(), $grantType, $clientEntity, $userIdentifier, $authCodeId),
            ...$workspaceScopes->values()->all(),
        ];
    }
}
