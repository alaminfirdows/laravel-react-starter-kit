<?php

namespace App\Mcp\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Scope;

/**
 * Passport authorize that always shows the consent screen, so each token gets a workspace picked by the user.
 */
class WorkspaceAuthorizationController extends AuthorizationController
{
    /**
     * Never auto-approve from earlier tokens: they may be bound to another workspace or to none.
     *
     * @param  Scope[]  $scopes
     */
    protected function hasGrantedScopes(Authenticatable $user, Client $client, array $scopes): bool
    {
        return false;
    }
}
