<?php

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Enums\PackReviewStatus;
use App\Domain\Catalog\Enums\PackVisibility;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Queries\AvailablePacks;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->task = CatalogTask::factory()->create(['title' => 'Pricing page']);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create(['phase' => ProjectPhase::Planning]);

    $this->otherUser = User::factory()->create();
    $this->otherWorkspace = Workspace::factory()->ownedBy($this->otherUser)->create(['slug' => 'other']);
    $this->otherProject = Project::factory()->forWorkspace($this->otherWorkspace)->create(['phase' => ProjectPhase::Planning]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function communityPackPayload(CatalogTask $task, array $overrides = []): array
{
    return [
        'name' => 'Launch kit',
        'description_md' => 'Our launch tasks',
        'phase' => ProjectPhase::Planning->value,
        'visibility' => PackVisibility::Private->value,
        'items' => [$task->key],
        ...$overrides,
    ];
}

/**
 * @return list<string>
 */
function availablePackKeys(Project $project): array
{
    return app(AvailablePacks::class)->handle($project)->pluck('key')->all();
}

test('private pack is listed for its owner but never for other workspaces', function () {
    $this->actingAs($this->user)->post('/acme/packs', communityPackPayload($this->task))->assertSessionHasNoErrors();

    $pack = Pack::query()->sole();

    expect($pack->owner_workspace_id)->toBe($this->workspace->id)
        ->and($pack->status)->toBe(CatalogStatus::Published)
        ->and($pack->review_status)->toBeNull()
        ->and($pack->items()->count())->toBe(1)
        ->and(availablePackKeys($this->project))->toBe([$pack->key])
        ->and(availablePackKeys($this->otherProject))->toBe([]);

    $this->actingAs($this->otherUser)->get('/other/packs')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('packs/index')->has('packs', 0));
});

test('public pack is hidden from others until an admin approves it', function () {
    $this->actingAs($this->user)
        ->post('/acme/packs', communityPackPayload($this->task, ['visibility' => PackVisibility::Public->value]))
        ->assertSessionHasNoErrors();

    $pack = Pack::query()->sole();

    expect($pack->status)->toBe(CatalogStatus::Draft)
        ->and($pack->review_status)->toBe(PackReviewStatus::Pending)
        ->and(availablePackKeys($this->project))->toBe([$pack->key])
        ->and(availablePackKeys($this->otherProject))->toBe([]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/community-packs')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/community-packs/index')
            ->has('packs', 1)
            ->where('packs.0.key', $pack->key)
            ->where('packs.0.items.0.title', 'Pricing page')
        );

    $this->actingAs($admin)
        ->post("/admin/community-packs/{$pack->key}/review", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($pack->refresh()->status)->toBe(CatalogStatus::Published)
        ->and($pack->review_status)->toBe(PackReviewStatus::Approved)
        ->and(availablePackKeys($this->otherProject))->toBe([$pack->key]);
});

test('rejected pack keeps the note and stays hidden, editing sends it for review again', function () {
    $this->actingAs($this->user)
        ->post('/acme/packs', communityPackPayload($this->task, ['visibility' => PackVisibility::Public->value]))
        ->assertSessionHasNoErrors();
    $pack = Pack::query()->sole();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post("/admin/community-packs/{$pack->key}/review", ['decision' => 'rejected'])
        ->assertSessionHasErrors('note');

    $this->actingAs($admin)
        ->post("/admin/community-packs/{$pack->key}/review", ['decision' => 'rejected', 'note' => 'Too vague'])
        ->assertSessionHasNoErrors();

    expect($pack->refresh()->review_status)->toBe(PackReviewStatus::Rejected)
        ->and($pack->review_note)->toBe('Too vague')
        ->and(availablePackKeys($this->otherProject))->toBe([]);

    $this->actingAs($this->user)
        ->patch("/acme/packs/{$pack->key}", communityPackPayload($this->task, ['visibility' => PackVisibility::Public->value, 'name' => 'Launch kit v2']))
        ->assertSessionHasNoErrors();

    expect($pack->refresh()->review_status)->toBe(PackReviewStatus::Pending)
        ->and($pack->status)->toBe(CatalogStatus::Draft)
        ->and($pack->version)->toBe(2);
});

test('another workspace cannot open or change the pack', function () {
    $this->actingAs($this->user)->post('/acme/packs', communityPackPayload($this->task))->assertSessionHasNoErrors();
    $pack = Pack::query()->sole();

    $this->actingAs($this->otherUser)->get("/other/packs/{$pack->key}/edit")->assertNotFound();
    $this->actingAs($this->otherUser)->patch("/other/packs/{$pack->key}", communityPackPayload($this->task))->assertNotFound();
});

test('non admin cannot review packs', function () {
    $pack = Pack::factory()->create([
        'owner_workspace_id' => $this->workspace->id,
        'visibility' => PackVisibility::Public,
        'status' => CatalogStatus::Draft,
        'review_status' => PackReviewStatus::Pending,
    ]);

    $this->actingAs($this->user)->get('/admin/community-packs')->assertForbidden();
    $this->actingAs($this->user)->post("/admin/community-packs/{$pack->key}/review", ['decision' => 'approved'])->assertForbidden();

    expect($pack->refresh()->status)->toBe(CatalogStatus::Draft);
});

test('pack items must be published root catalog tasks', function () {
    $subtask = CatalogTask::factory()->childOf($this->task)->create();
    $draft = CatalogTask::factory()->create(['status' => CatalogStatus::Draft]);

    $this->actingAs($this->user)
        ->post('/acme/packs', communityPackPayload($this->task, ['items' => [$subtask->key, $draft->key]]))
        ->assertSessionHasErrors(['items.0', 'items.1']);
});

test('admin pack list excludes community packs', function () {
    $official = Pack::factory()->create();
    Pack::factory()->create(['owner_workspace_id' => $this->workspace->id]);

    $this->actingAs(User::factory()->admin()->create())->get('/admin/packs')
        ->assertInertia(fn (Assert $page) => $page->has('packs', 1)->where('packs.0.key', $official->key));
});
