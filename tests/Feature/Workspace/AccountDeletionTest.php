<?php

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

test('account deletion is blocked while owning a shared workspace', function () {
    $user = User::factory()->create();
    Workspace::factory()->ownedBy($user)->withMember(User::factory()->create(), WorkspaceRole::Member)->create(['name' => 'Shared']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('password');

    expect($user->fresh())->not->toBeNull();
});

test('account deletion removes owned workspaces without other members', function () {
    $user = User::factory()->create();
    $personal = Workspace::factory()->personal()->ownedBy($user)->create();
    $solo = Workspace::factory()->ownedBy($user)->create();

    $other = Workspace::factory()->create();
    $other->memberships()->create(['user_id' => $user->id, 'role' => WorkspaceRole::Member]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull()
        ->and(Workspace::withTrashed()->find($personal->id))->toBeNull()
        ->and(Workspace::withTrashed()->find($solo->id))->toBeNull()
        ->and($other->memberships()->count())->toBe(1);
});
