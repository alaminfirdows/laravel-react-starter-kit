<?php

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->owner)->create(['name' => 'Acme', 'slug' => 'acme']);
});

test('settings root redirects to general', function () {
    $this->actingAs($this->owner)
        ->get('/acme/settings')
        ->assertRedirect(route('workspace.settings.edit', $this->workspace));
});

test('general settings page renders', function () {
    $this->actingAs($this->owner)
        ->get(route('workspace.settings.edit', $this->workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/settings/general')
            ->where('workspace.slug', 'acme')
            ->where('workspacePermissions.canUpdateWorkspace', true));
});

test('owner can update name and slug', function () {
    $this->actingAs($this->owner)
        ->patch(route('workspace.settings.update', $this->workspace), ['name' => 'Acme Inc', 'slug' => 'acme-inc'])
        ->assertRedirect('/acme-inc/settings/general');

    expect($this->workspace->fresh())
        ->name->toBe('Acme Inc')
        ->slug->toBe('acme-inc');
});

test('slug must be valid, not reserved and unique including deleted workspaces', function (string $slug) {
    Workspace::factory()->create(['slug' => 'taken'])->delete();

    $this->actingAs($this->owner)
        ->patch(route('workspace.settings.update', $this->workspace), ['name' => 'Acme', 'slug' => $slug])
        ->assertSessionHasErrors('slug');
})->with(['taken', 'dashboard', 'settings', 'invitations', 'login', '12345', 'Bad Slug', '-acme', 'a']);

test('generated slugs skip reserved and used slugs', function () {
    Workspace::factory()->create(['slug' => 'beta'])->delete();

    expect(Workspace::factory()->create(['name' => 'Beta'])->slug)->not->toBe('beta')
        ->and(Workspace::factory()->create(['name' => 'Dashboard'])->slug)->not->toBe('dashboard');
});

test('members cannot update settings', function () {
    $member = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);

    $this->actingAs($member)
        ->patch(route('workspace.settings.update', $this->workspace), ['name' => 'X', 'slug' => 'acme'])
        ->assertForbidden();
});

test('owner can upload and remove the logo', function () {
    Storage::fake('public');

    $this->actingAs($this->owner)
        ->post(route('workspace.settings.logo.update', $this->workspace), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertRedirect(route('workspace.settings.edit', $this->workspace));

    $path = $this->workspace->fresh()->logo_path;

    expect($path)->toStartWith("workspace-logos/{$this->workspace->id}/");
    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->owner)
        ->delete(route('workspace.settings.logo.destroy', $this->workspace))
        ->assertRedirect(route('workspace.settings.edit', $this->workspace));

    expect($this->workspace->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('logo must be a raster image', function () {
    Storage::fake('public');

    $this->actingAs($this->owner)
        ->post(route('workspace.settings.logo.update', $this->workspace), [
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('logo');
});

test('owner can delete the workspace after typing its name', function () {
    $personal = Workspace::factory()->personal()->ownedBy($this->owner)->create();

    $this->actingAs($this->owner)
        ->delete(route('workspace.settings.destroy', $this->workspace), ['name' => 'Wrong'])
        ->assertSessionHasErrors('name');

    $this->actingAs($this->owner)
        ->delete(route('workspace.settings.destroy', $this->workspace), ['name' => 'Acme'])
        ->assertRedirect(route('dashboard', $personal));

    expect($this->workspace->fresh()->trashed())->toBeTrue()
        ->and($this->owner->fresh()->current_workspace_id)->toBe($personal->id);
});

test('personal workspace cannot be deleted', function () {
    $personal = Workspace::factory()->personal()->ownedBy($this->owner)->create();

    $this->actingAs($this->owner)
        ->delete(route('workspace.settings.destroy', $personal), ['name' => $personal->name])
        ->assertForbidden();
});

test('users can create a workspace', function () {
    $this->actingAs($this->owner)
        ->post(route('workspaces.store'), ['name' => 'New Co'])
        ->assertRedirect('/new-co/dashboard');

    $workspace = Workspace::firstWhere('slug', 'new-co');

    expect($this->owner->workspaceRole($workspace))->toBe(WorkspaceRole::Owner)
        ->and($this->owner->fresh()->current_workspace_id)->toBe($workspace->id);
});
