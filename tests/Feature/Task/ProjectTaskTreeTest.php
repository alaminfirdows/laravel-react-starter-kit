<?php

use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Queries\ProjectTaskTree;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;

beforeEach(function () {
    $workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);
    $this->project = Project::factory()->forWorkspace($workspace)->create();
});

test('groups root tasks by category with nested children and progress', function () {
    CatalogCategory::factory()->create(['key' => 'launch', 'name' => 'Launch', 'sort_order' => 2]);
    CatalogCategory::factory()->create(['key' => 'idea', 'name' => 'Idea', 'sort_order' => 1]);

    Task::factory()->forProject($this->project)->create(['category_key' => 'launch', 'title' => 'Launch']);
    $idea = Task::factory()->forProject($this->project)->create(['category_key' => 'idea', 'title' => 'Idea']);
    Task::factory()->childOf($idea)->done()->create(['title' => 'Interview']);
    Task::factory()->childOf($idea)->create(['title' => 'Survey']);

    $tree = app(ProjectTaskTree::class)->handle($this->project);

    expect(array_map(fn ($group) => $group->key, $tree->groups))->toBe(['idea', 'launch'])
        ->and($tree->groups[0]->tasks->first()->children)->toHaveCount(2)
        ->and($tree->groups[0]->progressPct)->toBe(50)
        ->and($tree->progressPct)->toBe(33);
});

test('tasks without a known category go to Other, last', function () {
    CatalogCategory::factory()->create(['key' => 'idea', 'name' => 'Idea', 'sort_order' => 1]);
    Task::factory()->forProject($this->project)->create(['category_key' => null]);
    Task::factory()->forProject($this->project)->create(['category_key' => 'idea']);

    $tree = app(ProjectTaskTree::class)->handle($this->project);

    expect(array_map(fn ($group) => $group->name, $tree->groups))->toBe(['Idea', 'Other']);
});

test('serializes nested nodes for the sidebar', function () {
    $root = Task::factory()->forProject($this->project)->create(['title' => 'Root']);
    Task::factory()->childOf($root)->create(['title' => 'Child']);

    $json = json_decode(json_encode(app(ProjectTaskTree::class)->handle($this->project)->toArray()), true);

    expect($json['groups'][0]['tasks'][0])->toMatchArray(['title' => 'Root', 'status' => 'todo', 'progressPct' => 0])
        ->and($json['groups'][0]['tasks'][0]['children'][0]['title'])->toBe('Child')
        ->and($json['groups'][0]['tasks'][0]['children'][0]['children'])->toBe([]);
});
