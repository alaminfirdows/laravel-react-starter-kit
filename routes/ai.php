<?php

use App\Mcp\Http\RegisterOAuthClientController;
use App\Mcp\Servers\FounderServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

// Replaces the package route: reserved client names and a per-IP limit.
Route::post('oauth/register', RegisterOAuthClientController::class)
    ->middleware('throttle:oauth-register');

Mcp::web('/mcp/founder', FounderServer::class)
    ->middleware(['auth:api', 'throttle:mcp'])
    ->name('mcp.founder');

Mcp::local('founder', FounderServer::class);
