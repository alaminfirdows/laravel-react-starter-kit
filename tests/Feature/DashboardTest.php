<?php

use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $workspace = Workspace::factory()->create();

    $this->get(route('dashboard', $workspace))->assertRedirect(route('login'));
});

test('members can visit the workspace dashboard', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->ownedBy($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard', $workspace))
        ->assertOk();
});

test('dashboard redirects to the current workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->ownedBy($user)->create();
    $user->switchWorkspace($workspace);

    $this->actingAs($user)
        ->get(route('dashboard.redirect'))
        ->assertRedirect(route('dashboard', $workspace));
});

test('dashboard creates a personal workspace when the user has none', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard.redirect'));

    $personal = $user->fresh()->personalWorkspace();
    expect($personal)->not->toBeNull();
    $response->assertRedirect(route('dashboard', $personal));
});
