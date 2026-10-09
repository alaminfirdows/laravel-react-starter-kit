<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Servers\FounderServer;
use App\Mcp\Tools\WhoAmITool;
use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->personal = Workspace::factory()->personal()->ownedBy($this->user)->create(['name' => 'Personal']);
    $this->team = Workspace::factory()->withMember($this->user, WorkspaceRole::Viewer)->create(['name' => 'Team']);
    $this->user->forceFill(['current_workspace_id' => $this->personal->id])->save();
});

test('mcp endpoint rejects requests without a token', function () {
    $this->postJson('/mcp/founder', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

test('oauth discovery metadata is published', function () {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('scopes_supported', ['mcp:use']);
});

test('token without workspace scope uses the current workspace and client name', function () {
    Project::factory()->forWorkspace($this->personal)->create(['name' => 'Mine']);
    Project::factory()->forWorkspace($this->team)->create(['name' => 'Theirs']);
    $client = Client::factory()->create(['name' => 'Claude']);
    Passport::actingAs($this->user, ['mcp:use'], 'api', $client);

    FounderServer::tool(WhoAmITool::class)
        ->assertOk()
        ->assertSee(['Workspace: Personal', 'role: owner', 'Client: Claude', 'Mine'])
        ->assertDontSee('Theirs');
});

test('workspace scope binds the token to that workspace', function () {
    Project::factory()->forWorkspace($this->team)->create(['name' => 'Theirs']);
    Passport::actingAs($this->user, ['mcp:use', 'workspace:'.$this->team->id]);

    FounderServer::tool(WhoAmITool::class)
        ->assertOk()
        ->assertSee(['Workspace: Team', 'role: viewer', 'Theirs', 'Client: MCP client']);
});

test('workspace scope for a workspace the user is not in is refused', function () {
    $other = Workspace::factory()->create();
    Passport::actingAs($this->user, ['mcp:use', 'workspace:'.$other->id]);

    FounderServer::tool(WhoAmITool::class)->assertHasErrors(['no access to a workspace']);
});

test('consent screen shows the client and workspace', function () {
    $client = Client::factory()->create(['name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'], 'grant_types' => ['authorization_code', 'refresh_token']]);

    $this->actingAs($this->user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('oauth/authorize')
            ->where('client.name', 'Claude')
            ->where('client.verified', true)
            ->where('client.redirectHosts', ['claude.ai'])
            ->where('state', 'xyz')
            ->where('workspace.name', 'Personal'));
});
