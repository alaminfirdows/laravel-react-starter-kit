<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Catalog\Actions\ImportCatalog;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->catalogEvents = fn () => Activity::withoutWorkspaceScope()->where('event', 'like', 'catalog.%');
});

test('admin catalog mutations record global activity with the admin as actor', function () {
    $task = CatalogTask::factory()->create(['version' => 1]);

    $this->actingAs($this->admin)->patch("/admin/catalog/{$task->key}", ['title' => 'New', 'priority' => 'p1']);
    $this->actingAs($this->admin)->post("/admin/catalog/{$task->key}/publish");
    $this->actingAs($this->admin)->post("/admin/catalog/{$task->key}/actions", [
        'key' => 'a1', 'title' => 'A', 'type' => 'manual', 'executor' => 'user',
    ]);
    $this->actingAs($this->admin)->post('/admin/prompts', ['key' => 'p1', 'title' => 'P', 'target' => 'chat', 'full_md' => 'Body']);
    $this->actingAs($this->admin)->post('/admin/packs', [
        'key' => 'pk', 'name' => 'Pack', 'phase' => 'selling', 'status' => 'draft', 'items' => [$task->key],
    ]);

    $events = ($this->catalogEvents)()->get()->keyBy('event');

    expect($events->keys()->sort()->values()->all())->toBe([
        'catalog.action_created', 'catalog.pack_created', 'catalog.prompt_created', 'catalog.task_published', 'catalog.task_updated',
    ])
        ->and($events->every(fn (Activity $a) => $a->workspace_id === null && $a->actor_id === $this->admin->id))->toBeTrue()
        ->and($events['catalog.task_updated']->properties)->toEqual(['key' => $task->key, 'version' => 2])
        ->and($events['catalog.task_updated']->subject_id)->toBe((string) $task->id)
        ->and($events['catalog.pack_created']->properties['key'])->toBe('pk');
});

test('a no-op catalog save records nothing and non admins cannot trigger events', function () {
    $task = CatalogTask::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post("/admin/catalog/{$task->key}/publish")
        ->assertForbidden();
    expect(($this->catalogEvents)()->exists())->toBeFalse();
});

test('import records one summary event without a subject', function () {
    $dir = storage_path('framework/testing/catalog-'.uniqid());
    File::copyDirectory(base_path('tests/Fixtures/catalog'), $dir);

    $counts = app(ImportCatalog::class)->handle($dir, base_path('tests/Fixtures/skills'));

    $event = ($this->catalogEvents)()->sole();
    expect($event->event)->toBe('catalog.imported')
        ->and($event->workspace_id)->toBeNull()
        ->and($event->subject_id)->toBeNull()
        ->and($event->properties)->toEqual($counts)
        ->and(Pack::count())->toBeGreaterThan(0);
});
