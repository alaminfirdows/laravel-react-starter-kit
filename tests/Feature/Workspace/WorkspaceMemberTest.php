<?php

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();

    $this->workspace = Workspace::factory()
        ->ownedBy($this->owner)
        ->withMember($this->admin, WorkspaceRole::Admin)
        ->withMember($this->member, WorkspaceRole::Member)
        ->create();
});

test('members page lists members with what the actor can do', function () {
    $this->actingAs($this->admin)
        ->get(route('workspace.members.index', $this->workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/settings/members')
            ->has('members', 3)
            ->where('members.0.role', 'owner')
            ->where('members.0.canUpdate', false)
            ->where('members.1.isCurrentUser', true)
            ->where('members.2.canUpdate', true)
            ->where('members.2.canRemove', true)
            ->has('assignableRoles', 2));
});

test('owner can change roles', function () {
    $this->actingAs($this->owner)
        ->patch(route('workspace.members.update', [$this->workspace, $this->member]), ['role' => 'admin'])
        ->assertRedirect(route('workspace.members.index', $this->workspace));

    expect($this->member->workspaceRole($this->workspace))->toBe(WorkspaceRole::Admin);
});

test('roles cannot be raised to owner or by peers', function () {
    $this->actingAs($this->owner)
        ->patch(route('workspace.members.update', [$this->workspace, $this->member]), ['role' => 'owner'])
        ->assertSessionHasErrors('role');

    $this->actingAs($this->admin)
        ->patch(route('workspace.members.update', [$this->workspace, $this->member]), ['role' => 'admin'])
        ->assertSessionHasErrors('role');

    $this->actingAs($this->admin)
        ->patch(route('workspace.members.update', [$this->workspace, $this->owner]), ['role' => 'viewer'])
        ->assertForbidden();

    expect($this->member->workspaceRole($this->workspace))->toBe(WorkspaceRole::Member)
        ->and($this->owner->workspaceRole($this->workspace))->toBe(WorkspaceRole::Owner);
});

test('admin can remove a member', function () {
    $this->member->switchWorkspace($this->workspace);

    $this->actingAs($this->admin)
        ->delete(route('workspace.members.destroy', [$this->workspace, $this->member]))
        ->assertRedirect(route('workspace.members.index', $this->workspace));

    expect($this->member->belongsToWorkspace($this->workspace))->toBeFalse()
        ->and($this->member->fresh()->current_workspace_id)->not->toBe($this->workspace->id);
});

test('admin cannot remove owner or another admin', function () {
    $otherAdmin = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $otherAdmin->id, 'role' => WorkspaceRole::Admin]);

    $this->actingAs($this->admin)
        ->delete(route('workspace.members.destroy', [$this->workspace, $this->owner]))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('workspace.members.destroy', [$this->workspace, $otherAdmin]))
        ->assertForbidden();
});

test('users that are not members are not found', function () {
    $stranger = User::factory()->create();

    $this->actingAs($this->owner)
        ->delete(route('workspace.members.destroy', [$this->workspace, $stranger]))
        ->assertNotFound();
});

test('member can leave and goes to the fallback workspace', function () {
    $personal = Workspace::factory()->personal()->ownedBy($this->member)->create();

    $this->actingAs($this->member)
        ->delete(route('workspace.members.leave', $this->workspace))
        ->assertRedirect(route('dashboard', $personal));

    expect($this->member->belongsToWorkspace($this->workspace))->toBeFalse();
});

test('owner cannot leave', function () {
    $this->actingAs($this->owner)
        ->delete(route('workspace.members.leave', $this->workspace))
        ->assertForbidden();
});

test('owner can transfer ownership with the password', function () {
    $this->actingAs($this->owner)
        ->post(route('workspace.members.transfer', [$this->workspace, $this->admin]), ['password' => 'password'])
        ->assertRedirect(route('workspace.members.index', $this->workspace));

    expect($this->workspace->fresh()->owner_id)->toBe($this->admin->id)
        ->and($this->admin->workspaceRole($this->workspace))->toBe(WorkspaceRole::Owner)
        ->and($this->owner->workspaceRole($this->workspace))->toBe(WorkspaceRole::Admin);
});

test('transfer needs the correct password and the owner role', function () {
    $this->actingAs($this->owner)
        ->post(route('workspace.members.transfer', [$this->workspace, $this->admin]), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->actingAs($this->admin)
        ->post(route('workspace.members.transfer', [$this->workspace, $this->member]), ['password' => 'password'])
        ->assertForbidden();

    expect($this->workspace->fresh()->owner_id)->toBe($this->owner->id);
});
