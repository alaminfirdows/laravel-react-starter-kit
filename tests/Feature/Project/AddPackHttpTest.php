<?php

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Actions\ApplyPack;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket', 'phase' => ProjectPhase::Planning]);
    $this->extra = Pack::factory()->withTasks(CatalogTask::factory()->create(['title' => 'Pricing page']))->create(['name' => 'Pricing']);
});

test('overview lists only extra packs for the project phase not yet applied', function () {
    $applied = Pack::factory()->create();
    app(ApplyPack::class)->handle($this->project, $applied);
    Pack::factory()->defaultFor(ProjectPhase::Planning)->create();
    Pack::factory()->create(['audience' => ['phase' => ProjectPhase::Selling->value]]);
    Pack::factory()->create(['status' => CatalogStatus::Draft]);

    $this->actingAs($this->user)->get('/acme/projects/rocket')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/overview')
            ->missing('availablePacks')
            ->reloadOnly('availablePacks', fn (Assert $reload) => $reload
                ->has('availablePacks', 1)
                ->where('availablePacks.0.key', $this->extra->key)
                ->where('availablePacks.0.itemsCount', 1)
            )
        );
});

test('adding a pack copies its tasks into the project', function () {
    $this->actingAs($this->user)
        ->from('/acme/projects/rocket')
        ->post('/acme/projects/rocket/packs', ['pack' => $this->extra->key])
        ->assertRedirect('/acme/projects/rocket')
        ->assertSessionHasNoErrors();

    expect(Task::query()->where('project_id', $this->project->id)->pluck('title')->all())->toBe(['Pricing page'])
        ->and($this->project->packs()->where('pack_id', $this->extra->id)->exists())->toBeTrue();
});

test('pack outside the project phase or already applied is refused', function () {
    $selling = Pack::factory()->create(['audience' => ['phase' => ProjectPhase::Selling->value]]);
    app(ApplyPack::class)->handle($this->project, $this->extra);

    $this->actingAs($this->user)->post('/acme/projects/rocket/packs', ['pack' => $selling->key])->assertSessionHasErrors('pack');
    $this->actingAs($this->user)->post('/acme/projects/rocket/packs', ['pack' => $this->extra->key])->assertSessionHasErrors('pack');
});

test('viewer cannot add packs', function () {
    $viewer = User::factory()->create();
    $this->workspace->memberships()->create(['user_id' => $viewer->id, 'role' => WorkspaceRole::Viewer, 'joined_at' => now()]);

    $this->actingAs($viewer)->post('/acme/projects/rocket/packs', ['pack' => $this->extra->key])->assertForbidden();

    expect(Task::query()->where('project_id', $this->project->id)->count())->toBe(0);
});
