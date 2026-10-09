<?php

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Models\Activity;
use App\Domain\Workspace\Actions\CreateWorkspace;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Exceptions\WorkspaceNotSetException;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();
    $this->viewer = User::factory()->create();

    $this->workspace = Workspace::factory()
        ->ownedBy($this->owner)
        ->withMember($this->admin, WorkspaceRole::Admin)
        ->withMember($this->member, WorkspaceRole::Member)
        ->withMember($this->viewer, WorkspaceRole::Viewer)
        ->create(['name' => 'Acme', 'slug' => 'acme']);

    $this->events = fn (string $event) => Activity::withoutWorkspaceScope()
        ->where('workspace_id', $this->workspace->id)
        ->where('event', $event);
});

test('invite, resend, accept and decline record activity without secrets', function () {
    $this->actingAs($this->viewer)
        ->post(route('workspace.invitations.store', $this->workspace), ['email' => 'new@example.com', 'role' => 'member'])
        ->assertForbidden();
    expect(($this->events)('workspace.member_invited')->exists())->toBeFalse();

    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.store', $this->workspace), ['email' => 'new@example.com', 'role' => 'member']);
    $invited = ($this->events)('workspace.member_invited')->sole();

    expect($invited->actor_id)->toBe($this->owner->id)
        ->and($invited->subject_id)->toBe($this->workspace->id)
        ->and($invited->properties)->toEqual(['email' => 'new@example.com', 'role' => 'member']);

    $invitation = $this->workspace->invitations()->sole();
    $this->actingAs($this->owner)
        ->post(route('workspace.invitations.resend', [$this->workspace, $invitation]));
    $resent = ($this->events)('workspace.invitation_resent')->sole();

    expect($resent->properties)->toEqual(['email' => 'new@example.com', 'role' => 'member'])
        ->and(json_encode($resent->properties))->not->toContain($invitation->fresh()->code);

    $invitee = User::factory()->create(['email' => 'new@example.com']);
    $this->actingAs($invitee)->post(route('invitations.accept', $invitation->fresh()));
    $accepted = ($this->events)('workspace.invitation_accepted')->sole();

    expect($accepted->actor_id)->toBe($invitee->id)
        ->and($accepted->properties)->toEqual(['email' => 'new@example.com', 'role' => 'member', 'user_id' => $invitee->id]);

    $declined = WorkspaceInvitation::factory()->for($this->workspace)->create(['email' => 'no@example.com', 'role' => WorkspaceRole::Viewer]);
    $decliner = User::factory()->create(['email' => 'no@example.com']);
    $this->actingAs($decliner)->delete(route('invitations.decline', $declined));

    expect(($this->events)('workspace.invitation_declined')->sole()->properties)
        ->toEqual(['email' => 'no@example.com', 'role' => 'viewer']);
});

test('role change, removal and ownership transfer record activity', function () {
    $this->actingAs($this->viewer)
        ->patch(route('workspace.members.update', [$this->workspace, $this->member]), ['role' => 'admin'])
        ->assertForbidden();
    expect(($this->events)('workspace.member_role_changed')->exists())->toBeFalse();

    $this->actingAs($this->owner)
        ->patch(route('workspace.members.update', [$this->workspace, $this->member]), ['role' => 'admin']);
    expect(($this->events)('workspace.member_role_changed')->sole()->properties)
        ->toEqual(['user_id' => $this->member->id, 'from' => 'member', 'to' => 'admin']);

    $this->actingAs($this->admin)
        ->delete(route('workspace.members.destroy', [$this->workspace, $this->viewer]));
    $removed = ($this->events)('workspace.member_removed')->sole();
    expect($removed->actor_id)->toBe($this->admin->id)
        ->and($removed->properties)->toEqual(['user_id' => $this->viewer->id, 'role' => 'viewer']);

    $this->actingAs($this->owner)
        ->post(route('workspace.members.transfer', [$this->workspace, $this->admin]), ['password' => 'password']);
    expect(($this->events)('workspace.ownership_transferred')->sole()->properties)
        ->toEqual(['from_user_id' => $this->owner->id, 'to_user_id' => $this->admin->id]);
});

test('settings update and delete record activity', function () {
    $this->actingAs($this->viewer)
        ->patch(route('workspace.settings.update', $this->workspace), ['name' => 'X', 'slug' => 'acme'])
        ->assertForbidden();
    expect(($this->events)('workspace.updated')->exists())->toBeFalse();

    $this->actingAs($this->owner)
        ->patch(route('workspace.settings.update', $this->workspace), ['name' => 'Acme Inc', 'slug' => 'acme']);
    expect(($this->events)('workspace.updated')->sole()->properties)
        ->toEqual(['changes' => ['name' => ['from' => 'Acme', 'to' => 'Acme Inc']]]);

    Workspace::factory()->personal()->ownedBy($this->owner)->create();
    $this->actingAs($this->owner)
        ->delete(route('workspace.settings.destroy', $this->workspace), ['name' => 'Acme Inc']);

    expect($this->workspace->fresh()->trashed())->toBeTrue()
        ->and(($this->events)('workspace.deleted')->sole()->properties)->toEqual(['name' => 'Acme Inc', 'slug' => 'acme']);
});

test('creating a workspace and changing its logo record activity', function () {
    $created = app(CreateWorkspace::class)->handle($this->owner, 'Beta Co');
    $event = Activity::withoutWorkspaceScope()->where('workspace_id', $created->id)->where('event', 'workspace.created')->sole();

    expect($event->actor_id)->toBe($this->owner->id)
        ->and($event->properties)->toEqual(['name' => 'Beta Co', 'slug' => $created->slug]);

    Storage::fake('public');
    $this->actingAs($this->viewer)
        ->post(route('workspace.settings.logo.update', $this->workspace), ['logo' => UploadedFile::fake()->image('l.png')])
        ->assertForbidden();
    expect(($this->events)('workspace.logo_updated')->exists())->toBeFalse();

    $this->actingAs($this->owner)
        ->post(route('workspace.settings.logo.update', $this->workspace), ['logo' => UploadedFile::fake()->image('l.png')]);
    $this->actingAs($this->owner)->delete(route('workspace.settings.logo.destroy', $this->workspace));

    expect(($this->events)('workspace.logo_updated')->orderBy('id')->get()->pluck('properties')->all())
        ->toEqual([['removed' => false], ['removed' => true]]);
});

test('a non-global event without any workspace throws instead of going global', function () {
    expect(fn () => app(ActivityRecorder::class)->record('x.y', new User))->toThrow(WorkspaceNotSetException::class);
});
