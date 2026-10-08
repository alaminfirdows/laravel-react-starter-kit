<?php

namespace App\Mcp\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Queries\ActiveMcpConnections;
use App\Mcp\Support\McpActor;
use Laravel\Passport\Token;

/**
 * Sign an MCP client out: revoke access and refresh token.
 */
class RevokeMcpConnection
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Token $token, Actor $actor): void
    {
        $token->revoke();
        $token->refreshToken?->revoke();

        $workspace = Workspace::query()->find(ActiveMcpConnections::workspaceId($token));

        if ($workspace !== null) {
            $this->activity->record('mcp.connection_revoked', $workspace, [
                'user_id' => $token->user_id,
                'client_name' => $token->client->name ?? McpActor::DEFAULT_CLIENT_NAME,
            ], $actor);
        }
    }
}
