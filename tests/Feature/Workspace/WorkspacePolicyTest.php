<?php

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

function userWithRole(Workspace $workspace, WorkspaceRole $role): User
{
    if ($role === WorkspaceRole::Owner) {
        return $workspace->owner;
    }

    $user = User::factory()->create();
    $workspace->memberships()->create(['user_id' => $user->id, 'role' => $role]);

    return $user;
}

test('workspace abilities per role', function (WorkspaceRole $role, array $expected) {
    $workspace = Workspace::factory()->create();
    $user = userWithRole($workspace, $role);

    foreach ($expected as $ability => $allowed) {
        expect($user->can($ability, $workspace))->toBe($allowed, "{$role->value} {$ability}");
    }
})->with([
    'owner' => [WorkspaceRole::Owner, [
        'view' => true, 'update' => true, 'delete' => true, 'transferOwnership' => true,
        'leave' => false, 'inviteMember' => true, 'cancelInvitation' => true,
        'updateAnyMember' => true, 'removeAnyMember' => true,
    ]],
    'admin' => [WorkspaceRole::Admin, [
        'view' => true, 'update' => true, 'delete' => false, 'transferOwnership' => false,
        'leave' => true, 'inviteMember' => true, 'cancelInvitation' => true,
        'updateAnyMember' => true, 'removeAnyMember' => true,
    ]],
    'member' => [WorkspaceRole::Member, [
        'view' => true, 'update' => false, 'delete' => false, 'transferOwnership' => false,
        'leave' => true, 'inviteMember' => false, 'cancelInvitation' => false,
        'updateAnyMember' => false, 'removeAnyMember' => false,
    ]],
    'viewer' => [WorkspaceRole::Viewer, [
        'view' => true, 'update' => false, 'delete' => false, 'transferOwnership' => false,
        'leave' => true, 'inviteMember' => false, 'cancelInvitation' => false,
        'updateAnyMember' => false, 'removeAnyMember' => false,
    ]],
]);

test('outsiders have no access', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    foreach (['view', 'update', 'delete', 'leave', 'inviteMember'] as $ability) {
        expect($user->can($ability, $workspace))->toBeFalse();
    }
});

test('personal workspace cannot be deleted, left, transferred or shared', function () {
    $workspace = Workspace::factory()->personal()->create();
    $owner = $workspace->owner;

    expect($owner->can('update', $workspace))->toBeTrue();

    foreach (['delete', 'leave', 'transferOwnership', 'inviteMember', 'updateAnyMember', 'removeAnyMember'] as $ability) {
        expect($owner->can($ability, $workspace))->toBeFalse($ability);
    }
});

test('actors can only manage members ranked below them', function () {
    $workspace = Workspace::factory()->create();
    $owner = $workspace->owner;
    $admin = userWithRole($workspace, WorkspaceRole::Admin);
    $otherAdmin = userWithRole($workspace, WorkspaceRole::Admin);
    $member = userWithRole($workspace, WorkspaceRole::Member);

    expect($owner->can('updateMember', [$workspace, $admin]))->toBeTrue()
        ->and($owner->can('removeMember', [$workspace, $owner]))->toBeFalse()
        ->and($admin->can('updateMember', [$workspace, $member]))->toBeTrue()
        ->and($admin->can('removeMember', [$workspace, $member]))->toBeTrue()
        ->and($admin->can('updateMember', [$workspace, $otherAdmin]))->toBeFalse()
        ->and($admin->can('removeMember', [$workspace, $owner]))->toBeFalse()
        ->and($member->can('removeMember', [$workspace, $member]))->toBeFalse();
});

test('assignable roles are below the actor', function () {
    expect(WorkspaceRole::Owner->assignableRoles())->toBe([WorkspaceRole::Admin, WorkspaceRole::Member, WorkspaceRole::Viewer])
        ->and(WorkspaceRole::Admin->assignableRoles())->toBe([WorkspaceRole::Member, WorkspaceRole::Viewer])
        ->and(WorkspaceRole::Member->assignableRoles())->toBe([WorkspaceRole::Viewer]);
});
