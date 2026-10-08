<?php

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
});

test('project is scoped to the current workspace', function () {
    Project::factory()->forWorkspace($this->workspace)->create();
    Project::factory()->forWorkspace(Workspace::factory()->create())->create();

    expect(Project::count())->toBe(1);
});

test('slug is unique per workspace only', function () {
    Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'acme']);
    Project::factory()->forWorkspace(Workspace::factory()->create())->create(['slug' => 'acme']);

    Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'acme']);
})->throws(QueryException::class);

test('invalid phase is rejected by the database', function () {
    $project = Project::factory()->forWorkspace($this->workspace)->create();

    DB::table('projects')->where('id', $project->id)->update(['phase' => 'dreaming']);
})->throws(QueryException::class);
