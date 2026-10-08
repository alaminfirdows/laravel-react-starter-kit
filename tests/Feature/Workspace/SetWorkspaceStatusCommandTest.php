<?php

use App\Domain\Workspace\Enums\WorkspaceStatus;
use App\Domain\Workspace\Models\Workspace;

test('command sets the workspace status', function () {
    $workspace = Workspace::factory()->create(['slug' => 'acme']);

    $this->artisan('workspace:status', ['slug' => 'acme', 'status' => 'suspended'])->assertSuccessful();

    expect($workspace->fresh()->status)->toBe(WorkspaceStatus::Suspended);
});

test('command rejects bad input', function (array $arguments) {
    Workspace::factory()->create(['slug' => 'acme']);
    Workspace::factory()->personal()->create(['slug' => 'mine']);

    $this->artisan('workspace:status', $arguments)->assertFailed();
})->with([
    'bad status' => [['slug' => 'acme', 'status' => 'paused']],
    'unknown slug' => [['slug' => 'nope', 'status' => 'active']],
    'personal' => [['slug' => 'mine', 'status' => 'suspended']],
]);
