<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;

function registerClient(string $name, string $redirectUri): TestResponse
{
    return test()->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [$redirectUri]]);
}

test('a redirect outside the allowlist is rejected', function () {
    registerClient('My tool', 'https://evil.example/cb')
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');

    registerClient('Claude', 'https://claude.ai/api/mcp/auth_callback')->assertCreated();
});

test('lookalike claude redirects are rejected', function (string $redirectUri) {
    registerClient('My tool', $redirectUri)->assertStatus(400);
})->with([
    'subdomain trick' => ['https://claude.ai.evil.com/cb'],
    'path trick' => ['https://evil.com/claude.ai/cb'],
    'userinfo trick' => ['https://claude.ai@evil.com/cb'],
    'other port' => ['https://claude.ai:8443/cb'],
]);

test('loopback redirects are allowed in production for claude code', function (string $redirectUri) {
    app()->detectEnvironment(fn () => 'production');
    config(['mcp' => require config_path('mcp.php')]);

    expect(app()->isProduction())->toBeTrue();
    registerClient('Claude Code (founder)', $redirectUri)->assertCreated();
})->with(['http://localhost:33418/callback', 'http://127.0.0.1:33418/callback', 'http://[::1]:33418/callback']);

test('reserved names need a claude redirect', function () {
    config(['mcp.redirect_domains' => ['https://claude.ai', 'https://partner.example']]);

    registerClient('claude', 'https://partner.example/cb')
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_client_metadata');
    registerClient('Founder OS sync', 'https://partner.example/cb')->assertStatus(400);
    registerClient('Clаude', 'https://partner.example/cb')->assertStatus(400);
    registerClient('C1aude', 'https://partner.example/cb')->assertStatus(400);
    registerClient('Ｃｌａｕｄｅ', 'https://partner.example/cb')->assertStatus(400);
    registerClient('Fоunder-0S', 'https://partner.example/cb')->assertStatus(400);

    registerClient('Partner tool', 'https://partner.example/cb')->assertCreated();
});

test('client registration is throttled per ip', function () {
    foreach (range(1, 10) as $attempt) {
        registerClient('Claude', 'https://claude.ai/api/mcp/auth_callback')->assertCreated();
    }

    registerClient('Claude', 'https://claude.ai/api/mcp/auth_callback')->assertTooManyRequests();
});

test('consent screen marks an unknown client unverified and shows its redirect host', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['name' => 'Partner tool', 'redirect_uris' => ['http://localhost:4000/cb'], 'grant_types' => ['authorization_code', 'refresh_token']]);

    $this->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'http://localhost:4000/cb',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('oauth/authorize')
            ->where('client.verified', false)
            ->where('client.redirectHosts', ['localhost:4000']));
});
