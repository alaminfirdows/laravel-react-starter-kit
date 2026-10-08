<?php

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Http\Middleware\DiscoverWorkspace;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->owner)->create();
});

test('members open the workspace and it becomes current', function () {
    $member = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);

    $this->actingAs($member)
        ->get(route('dashboard', $this->workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('currentWorkspace.slug', $this->workspace->slug)
            ->where('currentWorkspace.role', 'member'));

    expect($member->fresh()->current_workspace_id)->toBe($this->workspace->id);
});

test('unknown slug returns 404', function () {
    $this->actingAs($this->owner)->get('/no-such-workspace/dashboard')->assertNotFound();
});

test('non members are redirected to their fallback workspace on GET', function () {
    $stranger = User::factory()->create();
    $personal = Workspace::factory()->personal()->ownedBy($stranger)->create();

    $this->actingAs($stranger)
        ->get(route('dashboard', $this->workspace))
        ->assertRedirect(route('dashboard', $personal));
});

test('non members without workspaces are redirected to the workspace list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', $this->workspace))
        ->assertRedirect(route('workspaces.index'));
});

test('non members get 403 on writes', function () {
    $this->actingAs(User::factory()->create())
        ->patch(route('workspace.settings.update', $this->workspace), ['name' => 'X', 'slug' => 'x-x'])
        ->assertForbidden();
});

test('removed member is sent to the fallback workspace', function () {
    $member = User::factory()->create();
    $personal = Workspace::factory()->personal()->ownedBy($member)->create();
    $this->workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);
    $member->switchWorkspace($this->workspace);

    $this->workspace->memberships()->where('user_id', $member->id)->delete();

    $this->actingAs($member)
        ->get(route('dashboard', $this->workspace))
        ->assertRedirect(route('dashboard', $personal));

    expect($member->fresh()->current_workspace_id)->toBe($personal->id);
});

test('suspended and inactive workspaces render the suspended page', function (string $state) {
    $workspace = Workspace::factory()->{$state}()->ownedBy($this->owner)->create();

    $this->actingAs($this->owner)
        ->get(route('dashboard', $workspace))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('workspace/suspended'));
})->with(['suspended', 'inactive']);

test('minimum role parameter blocks lower roles', function () {
    Route::middleware(['web', 'auth', DiscoverWorkspace::class.':admin'])
        ->get('/{workspace}/admin-only', fn () => 'ok');

    $member = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);

    $this->actingAs($member)->get("/{$this->workspace->slug}/admin-only")->assertForbidden();
    $this->actingAs($this->owner)->get("/{$this->workspace->slug}/admin-only")->assertOk();
});

test('the context and URL defaults are set for the request', function () {
    Route::middleware(['web', 'auth', 'workspace'])
        ->get('/{workspace}/context-probe', fn () => [
            'id' => workspaceId(),
            'url' => route('workspace.settings.edit'),
        ]);

    $this->actingAs($this->owner)
        ->get("/{$this->workspace->slug}/context-probe")
        ->assertExactJson([
            'id' => $this->workspace->id,
            'url' => route('workspace.settings.edit', $this->workspace),
        ]);
});

test('guests are sent to login', function () {
    $this->get(route('workspace.settings.edit', $this->workspace))->assertRedirect(route('login'));
});
