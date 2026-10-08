<?php

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Enums\WorkspaceType;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration creates a personal workspace and makes it current', function () {
    $this->post(route('register.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::firstWhere('email', 'jane@example.com');
    $workspace = $user->personalWorkspace();

    expect($user->id)->toBeString()->toHaveLength(26)
        ->and($workspace->name)->toBe("Jane's Workspace")
        ->and($workspace->type)->toBe(WorkspaceType::Personal)
        ->and($workspace->owner_id)->toBe($user->id)
        ->and($user->workspaceRole($workspace))->toBe(WorkspaceRole::Owner)
        ->and($user->current_workspace_id)->toBe($workspace->id);
});
