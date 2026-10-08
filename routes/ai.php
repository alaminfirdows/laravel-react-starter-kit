<?php

use App\Mcp\Servers\FounderServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/founder', FounderServer::class)
    ->middleware(['auth:api', 'throttle:mcp'])
    ->name('mcp.founder');

Mcp::local('founder', FounderServer::class);
