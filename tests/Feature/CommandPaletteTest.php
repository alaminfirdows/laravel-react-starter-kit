<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create();
});

test('palette projects are not sent on a full visit', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard', $this->workspace))
        ->assertInertia(fn (Assert $page) => $page->missing('paletteProjects'));
});

test('palette projects load on demand for the current workspace only', function () {
    $other = Workspace::factory()->ownedBy($this->user)->create();
    $discovery = app(WorkspaceDiscoveryService::class);
    $discovery->runAs($this->workspace, fn () => Project::factory()->forWorkspace($this->workspace)->create(['name' => 'Rocket']));
    $discovery->runAs($other, fn () => Project::factory()->forWorkspace($other)->create(['name' => 'Elsewhere']));

    $this->actingAs($this->user)
        ->get(route('dashboard', $this->workspace))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('paletteProjects', fn (Assert $reload) => $reload
                ->has('paletteProjects', 1)
                ->where('paletteProjects.0.name', 'Rocket')));
});
