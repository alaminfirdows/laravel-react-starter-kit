<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Servers\FounderServer;
use App\Mcp\Tools\WhoAmITool;
use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

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

test('token without workspace scope is rejected', function () {
    Project::factory()->forWorkspace($this->personal)->create(['name' => 'Mine']);
    Passport::actingAs($this->user, ['mcp:use']);

    FounderServer::tool(WhoAmITool::class)->assertHasErrors(['not bound to a workspace']);
});

test('token for one workspace stays there after the web ui switches workspace', function () {
    Project::factory()->forWorkspace($this->personal)->create(['name' => 'Mine']);
    Project::factory()->forWorkspace($this->team)->create(['name' => 'Theirs']);
    $client = Client::factory()->create(['name' => 'Claude']);
    Passport::actingAs($this->user, ['mcp:use', 'workspace:'.$this->personal->id], 'api', $client);

    $this->user->forceFill(['current_workspace_id' => $this->team->id])->save();

    FounderServer::tool(WhoAmITool::class)
        ->assertOk()
        ->assertSee(['Workspace: Personal', 'role: owner', 'Client: Claude', 'Mine'])
        ->assertDontSee('Theirs');
});

test('token stops working after the user leaves its workspace', function () {
    Passport::actingAs($this->user, ['mcp:use', 'workspace:'.$this->team->id]);
    $this->team->memberships()->where('user_id', $this->user->id)->delete();

    FounderServer::tool(WhoAmITool::class)->assertHasErrors(['no access to a workspace']);
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

/**
 * @return array{client: Client, verifier: string, authToken: string}
 */
function startAuthorization(User $user): array
{
    $client = Client::factory()->asPublic()->create(['name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']]);
    $verifier = str_repeat('v', 64);
    $authToken = '';

    test()->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertInertia(function ($page) use (&$authToken) {
            $authToken = $page->toArray()['props']['authToken'];
        });

    return ['client' => $client, 'verifier' => $verifier, 'authToken' => $authToken];
}

test('consent screen lists the user workspaces with the current one as default', function () {
    $client = Client::factory()->create(['name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']]);

    $this->actingAs($this->user)->get('/oauth/authorize?'.http_build_query([
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
            ->where('workspaces', [['id' => $this->personal->id, 'name' => 'Personal'], ['id' => $this->team->id, 'name' => 'Team']])
            ->where('currentWorkspaceId', $this->personal->id));
});

test('approving binds the issued token to the chosen workspace', function () {
    ['client' => $client, 'verifier' => $verifier, 'authToken' => $authToken] = startAuthorization($this->user);

    $redirect = $this->post('/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $client->id,
        'auth_token' => $authToken,
        'workspace' => $this->team->id,
    ])->assertRedirect()->headers->get('Location');

    expect($redirect)->toStartWith('https://claude.ai/api/mcp/auth_callback');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

    $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk();

    expect(Token::query()->sole()->scopes)->toBe(['mcp:use', 'workspace:'.$this->team->id]);
});

test('approving a workspace the user is not a member of fails', function () {
    $other = Workspace::factory()->create();
    ['client' => $client, 'authToken' => $authToken] = startAuthorization($this->user);

    $this->post('/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $client->id,
        'auth_token' => $authToken,
        'workspace' => $other->id,
    ])->assertSessionHasErrors('workspace');

    expect(Token::query()->count())->toBe(0);
});
