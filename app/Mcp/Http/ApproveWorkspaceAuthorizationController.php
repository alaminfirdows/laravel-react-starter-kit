<?php

namespace App\Mcp\Http;

use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Support\McpActor;
use App\Mcp\Support\McpScopeRepository;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport approve with the workspace picked on the consent screen: the token gets "workspace:<id>"
 * for one workspace the user is a member of. Any workspace scope the client asked for is dropped.
 */
class ApproveWorkspaceAuthorizationController extends ApproveAuthorizationController
{
    /**
     * @throws ValidationException
     */
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $workspace = $this->memberWorkspace($request);
        $authRequest = $this->getAuthRequestFromSession($request);

        $authRequest->setScopes([
            ...array_filter(
                $authRequest->getScopes(),
                fn (ScopeEntityInterface $scope): bool => ! McpScopeRepository::isWorkspaceScope($scope->getIdentifier()),
            ),
            new Scope(McpActor::WORKSPACE_SCOPE_PREFIX.$workspace->id),
        ]);
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(fn () => $this->convertResponse(
            $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
        ), $authRequest->getGrantTypeId() === 'implicit');
    }

    /**
     * @throws ValidationException
     */
    private function memberWorkspace(Request $request): Workspace
    {
        $request->validate(['workspace' => ['required', 'string']]);

        $user = $request->user();
        $workspace = Workspace::query()->whereKey($request->string('workspace')->toString())->first();

        if (! $user instanceof User || $workspace === null || ! $workspace->isActive() || ! $user->belongsToWorkspace($workspace)) {
            throw ValidationException::withMessages(['workspace' => __('Choose a workspace you are a member of.')]);
        }

        return $workspace;
    }
}
