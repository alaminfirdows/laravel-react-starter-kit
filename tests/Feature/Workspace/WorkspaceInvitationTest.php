<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Notifications\WorkspaceInvitationNotification;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->owner)->create();
});

test('admins can see the invitations page', function () {
    $this->actingAs($this->owner)
        ->get(route('workspace.invitations.index', $this->workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/settings/invitations')
            ->has('assignableRoles', 3));
});

test('viewers do not see pending invitee emails', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer]);
    WorkspaceInvitation::factory()->for($this->workspace)->create(['email' => 'secret@acme.test']);

    $this->actingAs($viewer)
        ->get(route('workspace.invitations.index', $this->workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 0));

    $this->actingAs($this->owner)
        ->get(route('workspace.invitations.index', $this->workspace))
        ->assertInertia(fn (Assert $page) => $page->where('invitations.0.email', 'secret@acme.test'));
});

test('owner can invite by email and the mail is sent', function () {
    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.store', $this->workspace), [
            'email' => 'New@Example.com',
            'role' => 'admin',
        ])
        ->assertRedirect(route('workspace.invitations.index', $this->workspace));

    $invitation = $this->workspace->invitations()->sole();

    expect($invitation->email)->toBe('new@example.com')
        ->and($invitation->role)->toBe(WorkspaceRole::Admin)
        ->and($invitation->invited_by)->toBe($this->owner->id)
        ->and($invitation->code)->toHaveLength(64)
        ->and($invitation->expires_at->isSameDay(now()->addDays(3)))->toBeTrue();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        WorkspaceInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.com',
    );
});

test('admins cannot invite admins and members cannot invite', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $admin->id, 'role' => WorkspaceRole::Admin]);
    $this->workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);

    $this->actingAs($admin)
        ->post(route('workspace.invitations.store', $this->workspace), ['email' => 'a@example.com', 'role' => 'admin'])
        ->assertSessionHasErrors('role');

    $this->actingAs($member)
        ->post(route('workspace.invitations.store', $this->workspace), ['email' => 'a@example.com', 'role' => 'viewer'])
        ->assertForbidden();
});

test('existing members and pending invitations are rejected', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    $this->workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);
    WorkspaceInvitation::factory()->for($this->workspace)->create(['email' => 'pending@example.com']);

    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.store', $this->workspace), ['email' => 'MEMBER@example.com', 'role' => 'member'])
        ->assertSessionHasErrors('email');

    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.store', $this->workspace), ['email' => 'pending@example.com', 'role' => 'member'])
        ->assertSessionHasErrors('email');
});

test('expired invitation can be replaced by a new one', function () {
    WorkspaceInvitation::factory()->for($this->workspace)->expired()->create(['email' => 'old@example.com']);

    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.store', $this->workspace), ['email' => 'old@example.com', 'role' => 'member'])
        ->assertSessionHasNoErrors();

    expect($this->workspace->invitations()->sole()->isPending())->toBeTrue();
});

test('personal workspaces cannot invite', function () {
    $personal = Workspace::factory()->personal()->ownedBy($this->owner)->create();

    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.store', $personal), ['email' => 'a@example.com', 'role' => 'member'])
        ->assertForbidden();
});

test('resend makes a new code and a new expiry', function () {
    $invitation = WorkspaceInvitation::factory()->for($this->workspace)->expired()->create();
    $oldCode = $invitation->code;

    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.resend', [$this->workspace, $invitation]))
        ->assertRedirect(route('workspace.invitations.index', $this->workspace));

    $invitation->refresh();

    expect($invitation->code)->not->toBe($oldCode)
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentTimes(WorkspaceInvitationNotification::class, 1);
});

test('invitations of another workspace cannot be touched', function () {
    $other = WorkspaceInvitation::factory()->create();

    $this->actingAs($this->owner)
        ->delete(route('workspace.invitations.destroy', [$this->workspace, $other]))
        ->assertNotFound();

    expect($other->fresh())->not->toBeNull()
        ->and(Activity::withoutWorkspaceScope()->where('event', 'workspace.invitation_revoked')->exists())->toBeFalse();
});

test('owner can cancel an invitation', function () {
    $invitation = WorkspaceInvitation::factory()->for($this->workspace)->create();

    $this->actingAs($this->owner)
        ->delete(route('workspace.invitations.destroy', [$this->workspace, $invitation]))
        ->assertRedirect(route('workspace.invitations.index', $this->workspace));

    $activity = Activity::withoutWorkspaceScope()->where('event', 'workspace.invitation_revoked')->sole();

    expect($invitation->fresh())->toBeNull()
        ->and($activity->workspace_id)->toBe($this->workspace->id)
        ->and($activity->actor_id)->toBe($this->owner->id)
        ->and($activity->properties['email'])->toBe($invitation->email);
});

test('invitations are throttled at 20 per hour per workspace', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->actingAs($this->owner)
            ->post(route('workspace.invitations.store', $this->workspace), ['email' => "u{$i}@example.com", 'role' => 'member'])
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.store', $this->workspace), ['email' => 'u21@example.com', 'role' => 'member'])
        ->assertTooManyRequests();
});

test('guests see the invitation and the URL is kept as intended', function () {
    $invitation = WorkspaceInvitation::factory()->for($this->workspace)->create();

    $this->get(route('invitations.show', $invitation))
        ->assertOk()
        ->assertSessionHas('url.intended', route('invitations.show', $invitation))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invitations/show')
            ->where('isGuest', true)
            ->where('invitation.status', 'pending')
            ->where('invitation.workspaceName', $this->workspace->name));
});

test('invitation page shows expired and accepted states', function (string $state, string $status) {
    $invitation = WorkspaceInvitation::factory()->for($this->workspace)->{$state}()->create();

    $this->get(route('invitations.show', $invitation))
        ->assertInertia(fn (Assert $page) => $page->where('invitation.status', $status));
})->with([['expired', 'expired'], ['accepted', 'accepted']]);

test('unknown code returns 404', function () {
    $this->get('/invitations/nope')->assertNotFound();
});

test('invited user can accept', function () {
    $user = User::factory()->create(['email' => 'Invitee@Example.com']);
    $invitation = WorkspaceInvitation::factory()->for($this->workspace)->create([
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Admin,
    ]);

    $this->actingAs($user)
        ->post(route('invitations.accept', $invitation))
        ->assertRedirect(route('dashboard', $this->workspace));

    expect($user->workspaceRole($this->workspace))->toBe(WorkspaceRole::Admin)
        ->and($user->fresh()->current_workspace_id)->toBe($this->workspace->id)
        ->and($invitation->fresh()->isAccepted())->toBeTrue();
});

test('accept fails for another email, expired or used invitation', function (string $state, string $email) {
    $user = User::factory()->create(['email' => $email]);
    $invitation = WorkspaceInvitation::factory()->for($this->workspace)
        ->when($state !== 'pending', fn ($factory) => $factory->{$state}())
        ->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->post(route('invitations.accept', $invitation))
        ->assertRedirect(route('invitations.show', $invitation));

    expect($user->belongsToWorkspace($this->workspace))->toBeFalse();
})->with([
    'email mismatch' => ['pending', 'other@example.com'],
    'expired' => ['expired', 'invitee@example.com'],
    'accepted' => ['accepted', 'invitee@example.com'],
]);

test('invited user can decline', function () {
    $user = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = WorkspaceInvitation::factory()->for($this->workspace)->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->delete(route('invitations.decline', $invitation))
        ->assertRedirect(route('workspaces.index'));

    expect($invitation->fresh())->toBeNull()
        ->and($user->belongsToWorkspace($this->workspace))->toBeFalse();
});

test('other users cannot decline', function () {
    $user = User::factory()->create();
    $invitation = WorkspaceInvitation::factory()->for($this->workspace)->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->delete(route('invitations.decline', $invitation))
        ->assertRedirect(route('invitations.show', $invitation));

    expect($invitation->fresh())->not->toBeNull();
});

test('old accepted and expired invitations are pruned', function () {
    $old = WorkspaceInvitation::factory()->for($this->workspace)->create(['expires_at' => now()->subDays(31)]);
    $oldAccepted = WorkspaceInvitation::factory()->for($this->workspace)->create(['accepted_at' => now()->subDays(31)]);
    $recent = WorkspaceInvitation::factory()->for($this->workspace)->expired()->create();
    $pending = WorkspaceInvitation::factory()->for($this->workspace)->create();

    $this->artisan('model:prune', ['--model' => [WorkspaceInvitation::class]])->assertSuccessful();

    expect($old->fresh())->toBeNull()
        ->and($oldAccepted->fresh())->toBeNull()
        ->and($recent->fresh())->not->toBeNull()
        ->and($pending->fresh())->not->toBeNull();
});

test('workspaces page lists pending invitations for the user', function () {
    $user = User::factory()->create(['email' => 'invitee@example.com']);
    WorkspaceInvitation::factory()->for($this->workspace)->create(['email' => 'invitee@example.com']);
    WorkspaceInvitation::factory()->for($this->workspace)->expired()->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->get(route('workspaces.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/index')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitationsCount', 1));
});
