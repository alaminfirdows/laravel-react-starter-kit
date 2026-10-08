<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    $this->project = app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => Project::factory()->forWorkspace($this->workspace)->draft()->create(['slug' => 'rocket']));
    $this->actingAs($this->user);
});

test('walks the wizard and activates on the last step', function () {
    $this->get('/acme/projects/rocket/setup/identity')
        ->assertInertia(fn (Assert $page) => $page->component('projects/setup')->where('step', 'identity')->has('steps', 4));

    $this->patch('/acme/projects/rocket/setup/identity', ['name' => 'Rocket', 'one_liner' => 'Rockets for cats'])
        ->assertRedirect('/acme/projects/rocket/setup/business');
    $this->patch('/acme/projects/rocket/setup/business', ['business_model' => 'b2c_app', 'stage' => 'idea'])
        ->assertRedirect('/acme/projects/rocket/setup/market');
    $this->patch('/acme/projects/rocket/setup/market', ['primary_market' => 'DE'])
        ->assertRedirect('/acme/projects/rocket/setup/goals');
    $this->patch('/acme/projects/rocket/setup/goals', ['goals' => [['title' => '10 customers']]])
        ->assertRedirect('/acme/projects/rocket');

    expect($this->project->fresh()->isDraft())->toBeFalse()
        ->and($this->project->fresh()->goals)->toBe([['title' => '10 customers']]);
});

test('finishing with missing fields sends the user back to that step', function () {
    $this->patch('/acme/projects/rocket/setup/goals', ['goals' => []])
        ->assertRedirect('/acme/projects/rocket/setup/identity')
        ->assertSessionHasErrors('one_liner');
});

test('unknown step is 404', function () {
    $this->get('/acme/projects/rocket/setup/finance')->assertNotFound();
});

test('step validation errors', function () {
    $this->patch('/acme/projects/rocket/setup/market', ['primary_market' => 'germany'])
        ->assertSessionHasErrors('primary_market');
});

test('logo upload', function () {
    Storage::fake('public');

    $this->post('/acme/projects/rocket/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
        ->assertRedirect();

    expect($this->project->fresh()->brand->logo_media_id)->not->toBeNull();
});

test('logo rejects non images', function () {
    $this->post('/acme/projects/rocket/logo', ['logo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('logo');
});
