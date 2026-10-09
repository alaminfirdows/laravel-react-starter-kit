<?php

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('non admin cannot open or change the catalog', function () {
    $task = CatalogTask::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/catalog')->assertForbidden();
    $this->actingAs($user)->patch("/admin/catalog/{$task->key}", ['title' => 'X', 'priority' => 'p1'])->assertForbidden();
    $this->actingAs($user)->get('/admin/prompts')->assertForbidden();
    $this->actingAs($user)->get('/admin/packs')->assertForbidden();
});

test('catalog index shows the category tree with nested tasks', function () {
    $category = CatalogCategory::factory()->create();
    $root = CatalogTask::factory()->create(['category_id' => $category->id]);
    CatalogTask::factory()->childOf($root)->create();

    $this->actingAs($this->admin)
        ->get('/admin/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/catalog/index')
            ->where('categories.0.tasks.0.key', $root->key)
            ->has('categories.0.tasks.0.children', 1)
        );
});

test('admin creates a draft task and then a subtask', function () {
    $category = CatalogCategory::factory()->create();

    $this->actingAs($this->admin)
        ->post('/admin/catalog', [
            'key' => 'define-icp',
            'category_id' => $category->id,
            'title' => 'Define ICP',
            'body_md' => 'Who buys?',
            'priority' => 'p1',
            'is_optional' => '1',
        ])
        ->assertRedirect('/admin/catalog/define-icp/edit');

    $task = CatalogTask::query()->where('key', 'define-icp')->firstOrFail();
    expect($task->status)->toBe(CatalogStatus::Draft)
        ->and($task->is_optional)->toBeTrue();

    $this->actingAs($this->admin)
        ->post('/admin/catalog', ['key' => 'icp-interviews', 'parent' => 'define-icp', 'title' => 'Interviews', 'priority' => 'p2'])
        ->assertRedirect('/admin/catalog/icp-interviews/edit');

    expect(CatalogTask::query()->where('key', 'icp-interviews')->value('parent_id'))->toBe($task->id);
});

test('create validates key, category and priority', function () {
    CatalogTask::factory()->create(['key' => 'taken']);

    $this->actingAs($this->admin)
        ->post('/admin/catalog', ['key' => 'taken', 'title' => '', 'priority' => 'urgent'])
        ->assertSessionHasErrors(['key', 'category_id', 'title', 'priority']);
});

test('edit page lazily loads prompt options', function () {
    $task = CatalogTask::factory()->create();
    PromptTemplate::factory()->create();

    $this->actingAs($this->admin)
        ->get("/admin/catalog/{$task->key}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/catalog/edit')
            ->where('task.key', $task->key)
            ->missing('promptOptions')
            ->reloadOnly('promptOptions', fn (Assert $reload) => $reload->has('promptOptions', 1))
        );
});

test('update bumps version and publish makes the task live', function () {
    $task = CatalogTask::factory()->create(['status' => CatalogStatus::Draft, 'version' => 1, 'title' => 'Old']);

    $this->actingAs($this->admin)
        ->patch("/admin/catalog/{$task->key}", ['title' => 'New', 'priority' => 'p1'])
        ->assertRedirect();

    expect($task->fresh()->version)->toBe(2)
        ->and($task->fresh()->title)->toBe('New');

    $this->actingAs($this->admin)
        ->post("/admin/catalog/{$task->key}/publish")
        ->assertRedirect();

    expect($task->fresh()->status)->toBe(CatalogStatus::Published)
        ->and($task->fresh()->published_at)->not->toBeNull();
});

test('admin adds and edits an action with prompt and json config', function () {
    $task = CatalogTask::factory()->create(['version' => 1]);
    $prompt = PromptTemplate::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/catalog/{$task->key}/actions", [
            'key' => 'draft-icp',
            'title' => 'Draft ICP',
            'type' => 'ai',
            'executor' => 'claude_desktop',
            'prompt' => $prompt->key,
            'config' => '{"check":"https"}',
            'is_required' => '1',
        ])
        ->assertRedirect();

    $action = $task->actions()->firstOrFail();
    expect($action->prompt_template_id)->toBe($prompt->id)
        ->and($action->config)->toBe(['check' => 'https'])
        ->and($task->fresh()->version)->toBe(2);

    $this->actingAs($this->admin)
        ->patch("/admin/catalog/{$task->key}/actions/draft-icp", [
            'key' => 'draft-icp',
            'title' => 'Draft the ICP',
            'type' => 'ai',
            'executor' => 'claude_desktop',
        ])
        ->assertRedirect();

    expect($action->fresh()->title)->toBe('Draft the ICP')
        ->and($action->fresh()->prompt_template_id)->toBeNull();
});

test('action of another task is not reachable through this task', function () {
    $task = CatalogTask::factory()->create();
    $other = CatalogAction::factory()->create(['key' => 'foreign']);

    $this->actingAs($this->admin)
        ->patch("/admin/catalog/{$task->key}/actions/foreign", ['key' => 'foreign', 'title' => 'X', 'type' => 'manual', 'executor' => 'user'])
        ->assertNotFound();

    expect($other->fresh()->title)->not->toBe('X');
});

test('invalid action config json is rejected', function () {
    $task = CatalogTask::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/catalog/{$task->key}/actions", ['key' => 'a', 'title' => 'A', 'type' => 'manual', 'executor' => 'user', 'config' => '{bad'])
        ->assertSessionHasErrors('config');
});

test('admin creates and updates a prompt template', function () {
    $this->actingAs($this->admin)
        ->post('/admin/prompts', [
            'key' => 'icp-prompt',
            'title' => 'ICP prompt',
            'target' => 'chat',
            'full_md' => 'Write the ICP.',
            'skill_keys' => 'icp-research, positioning',
        ])
        ->assertRedirect('/admin/prompts/icp-prompt/edit');

    $prompt = PromptTemplate::query()->where('key', 'icp-prompt')->firstOrFail();
    expect($prompt->skill_keys)->toBe(['icp-research', 'positioning']);

    $this->actingAs($this->admin)
        ->patch('/admin/prompts/icp-prompt', ['title' => 'ICP prompt', 'target' => 'cowork', 'full_md' => 'Write it better.'])
        ->assertRedirect();

    expect($prompt->fresh()->version)->toBe(2)
        ->and($prompt->fresh()->target)->toBe('cowork');
});

test('admin creates and updates a pack with task items', function () {
    [$first, $second] = CatalogTask::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->post('/admin/packs', [
            'key' => 'launch-extras',
            'name' => 'Launch extras',
            'phase' => 'selling',
            'status' => 'draft',
            'items' => [$first->key],
        ])
        ->assertRedirect('/admin/packs/launch-extras/edit');

    $pack = Pack::query()->where('key', 'launch-extras')->firstOrFail();
    expect($pack->items()->count())->toBe(1);

    $this->actingAs($this->admin)
        ->patch('/admin/packs/launch-extras', [
            'name' => 'Launch extras',
            'phase' => 'selling',
            'status' => 'published',
            'items' => [$first->key, $second->key],
        ])
        ->assertRedirect();

    expect($pack->fresh()->items()->count())->toBe(2)
        ->and($pack->fresh()->version)->toBe(2);

    $this->actingAs($this->admin)
        ->get('/admin/packs/launch-extras/edit')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/packs/edit')
            ->where('pack.items', [$first->key, $second->key])
        );
});

test('pack requires at least one existing task', function () {
    $this->actingAs($this->admin)
        ->post('/admin/packs', ['key' => 'empty', 'name' => 'Empty', 'phase' => 'planning', 'status' => 'draft', 'items' => ['missing']])
        ->assertSessionHasErrors('items.0');
});

test('resubmitting the own key on update is not a duplicate', function () {
    $this->actingAs($this->admin)->post('/admin/prompts', ['key' => 'same-key', 'title' => 'P', 'target' => 'chat', 'full_md' => 'Body'])->assertRedirect();

    $this->actingAs($this->admin)
        ->patch('/admin/prompts/same-key', ['key' => 'same-key', 'title' => 'P2', 'target' => 'chat', 'full_md' => 'Body'])
        ->assertSessionDoesntHaveErrors('key');
});
