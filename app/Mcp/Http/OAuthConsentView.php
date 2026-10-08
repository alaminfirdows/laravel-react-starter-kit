<?php

namespace App\Mcp\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Passport\Client;
use Laravel\Passport\Scope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport consent screen ("Connect Claude") rendered as an Inertia page.
 */
class OAuthConsentView
{
    /**
     * @param  array<string, mixed>  $parameters  client, user, scopes, request, authToken from Passport
     */
    public function __invoke(array $parameters): Response
    {
        ['client' => $client, 'user' => $user, 'scopes' => $scopes, 'request' => $request, 'authToken' => $authToken] = $parameters;

        abort_unless($client instanceof Client && $user instanceof User && $request instanceof Request && is_array($scopes), 500);

        return Inertia::render('oauth/authorize', [
            'client' => ['id' => (string) $client->getKey(), 'name' => $client->name],
            'scopes' => array_values(array_map(
                fn (Scope $scope): array => ['id' => $scope->id, 'description' => $scope->description],
                array_filter($scopes, fn (mixed $scope): bool => $scope instanceof Scope),
            )),
            'state' => $request->string('state')->toString(),
            'authToken' => (string) $authToken,
            'csrfToken' => csrf_token(),
            'workspace' => $user->resolveCurrentWorkspace()?->only(['id', 'name']),
        ])->toResponse($request);
    }
}
