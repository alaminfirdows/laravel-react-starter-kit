<?php

namespace App\Mcp\Http;

use App\Mcp\Support\ClientTrust;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/**
 * Dynamic client registration (RFC 7591) that keeps Claude and Founder OS names for Claude redirects.
 */
class RegisterOAuthClientController extends OAuthRegisterController
{
    public function __invoke(Request $request): JsonResponse
    {
        $name = $request->input('client_name') ?? $request->input('name');
        $redirectUris = $request->input('redirect_uris');
        $trust = app(ClientTrust::class);

        if (is_string($name) && $trust->isReservedName($name) && ! (is_array($redirectUris) && $trust->hasClaudeRedirects($redirectUris))) {
            return response()->json([
                'error' => 'invalid_client_metadata',
                'error_description' => 'This client name is reserved.',
            ], 400);
        }

        return parent::__invoke($request);
    }
}
