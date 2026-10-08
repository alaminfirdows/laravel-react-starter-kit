<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

test('access by role', function (WorkspaceRole $role, bool $view, bool $update, bool $delete) {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->withMember($user, $role)->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $project = Project::factory()->forWorkspace($workspace)->create();

    expect($user->can('view', $project))->toBe($view)
        ->and($user->can('update', $project))->toBe($update)
        ->and($user->can('delete', $project))->toBe($delete);
})->with([
    'admin' => [WorkspaceRole::Admin, true, true, true],
    'member' => [WorkspaceRole::Member, true, true, false],
    'viewer' => [WorkspaceRole::Viewer, true, false, false],
]);

test('non member cannot view', function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $project = Project::factory()->forWorkspace($workspace)->create();

    expect(User::factory()->create()->can('view', $project))->toBeFalse();
});
