<?php

use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

function mcpToken(User $user, array $scopes = ['mcp:use'], array $attributes = []): Token
{
    $client = Client::factory()->create(['name' => 'Claude']);

    return Token::forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => $scopes,
        'revoked' => false,
        'expires_at' => now()->addDay(),
        ...$attributes,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('page shows connector url and active connections only', function () {
    $workspace = Workspace::factory()->ownedBy($this->user)->create(['name' => 'Acme']);
    $active = mcpToken($this->user, ['mcp:use', 'workspace:'.$workspace->id]);
    mcpToken($this->user, attributes: ['revoked' => true]);
    mcpToken($this->user, attributes: ['expires_at' => now()->subMinute()]);
    mcpToken(User::factory()->create());

    $this->get(route('connect-claude.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/connect-claude')
            ->where('connectorUrl', route('mcp.founder'))
            ->has('connections', 1)
            ->where('connections.0.id', $active->id)
            ->where('connections.0.clientName', 'Claude')
            ->where('connections.0.workspaceName', 'Acme'));
});

test('revoke signs the client out', function () {
    $token = mcpToken($this->user);

    $this->delete(route('connect-claude.destroy', $token->id))->assertRedirect();

    expect($token->refresh()->revoked)->toBeTrue();
});

test('cannot revoke a token of another user', function () {
    $token = mcpToken(User::factory()->create());

    $this->delete(route('connect-claude.destroy', $token->id))->assertNotFound();

    expect($token->refresh()->revoked)->toBeFalse();
});
