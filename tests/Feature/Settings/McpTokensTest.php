<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Workspace\Actions\RemoveMember;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

function workspaceToken(User $user, Workspace $workspace): Token
{
    return Token::forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => Client::factory()->create(['name' => 'Claude'])->id,
        'scopes' => ['mcp:use', 'workspace:'.$workspace->id],
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);
}

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Olivia']);
    $this->admin = User::factory()->create(['name' => 'Ada']);
    $this->member = User::factory()->create(['name' => 'Mia']);
    $this->workspace = Workspace::factory()->ownedBy($this->owner)->create(['slug' => 'acme', 'name' => 'Acme']);
    foreach ([[$this->admin, WorkspaceRole::Admin], [$this->member, WorkspaceRole::Member]] as [$user, $role]) {
        $this->workspace->memberships()->create(['user_id' => $user->id, 'role' => $role, 'joined_at' => now()]);
        $user->forceFill(['current_workspace_id' => $this->workspace->id])->save();
    }
    $this->memberToken = workspaceToken($this->member, $this->workspace);
    $this->ownerToken = workspaceToken($this->owner, $this->workspace);
    workspaceToken($this->member, Workspace::factory()->create());
});

test('admin sees member tokens of the current workspace', function () {
    $this->actingAs($this->admin)
        ->get(route('connect-claude.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.workspace.slug', 'acme')
            ->has('team.connections', 2)
            ->where('team.connections', fn ($connections) => collect($connections)->firstWhere('userName', 'Mia')['canRevoke'] === true
                && collect($connections)->firstWhere('userName', 'Olivia')['canRevoke'] === false));
});

test('member does not see team tokens', function () {
    $this->actingAs($this->member)
        ->get(route('connect-claude.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('team', null)->has('connections', 2));
});

test('admin revokes a member token and activity is recorded', function () {
    $this->actingAs($this->admin)
        ->delete("/acme/settings/connections/{$this->memberToken->id}")
        ->assertRedirect();

    expect($this->memberToken->fresh()->revoked)->toBeTrue()
        ->and(Activity::withoutWorkspaceScope()->where('event', 'mcp.connection_revoked')->value('workspace_id'))->toBe($this->workspace->id);
});

test('admin cannot revoke owner token and member cannot revoke others', function () {
    $this->actingAs($this->admin)->delete("/acme/settings/connections/{$this->ownerToken->id}")->assertForbidden();
    $this->actingAs($this->member)->delete("/acme/settings/connections/{$this->ownerToken->id}")->assertForbidden();

    expect($this->ownerToken->fresh()->revoked)->toBeFalse();
});

test('token of another workspace is not found', function () {
    $other = Token::query()->where('user_id', $this->member->id)->whereKeyNot($this->memberToken->id)->sole();

    $this->actingAs($this->admin)->delete("/acme/settings/connections/{$other->id}")->assertNotFound();
});

test('removing a member revokes their workspace tokens only', function () {
    app(RemoveMember::class)->handle($this->workspace, $this->member);

    expect($this->memberToken->fresh()->revoked)->toBeTrue()
        ->and(Token::query()->where('user_id', $this->member->id)->where('revoked', false)->count())->toBe(1);
});

test('viewer cannot see or revoke member tokens', function () {
    $viewer = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);
    $viewer->forceFill(['current_workspace_id' => $this->workspace->id])->save();

    $this->actingAs($viewer)
        ->get(route('connect-claude.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('team', null));
    $this->actingAs($viewer)->delete("/acme/settings/connections/{$this->memberToken->id}")->assertForbidden();

    expect($this->memberToken->fresh()->revoked)->toBeFalse();
});
