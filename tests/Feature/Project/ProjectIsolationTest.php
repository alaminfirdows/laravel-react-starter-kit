<?php

use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->a = Workspace::factory()->ownedBy($this->alice)->create(['slug' => 'alpha']);
    $this->b = Workspace::factory()->create(['slug' => 'beta']);
    [$this->projectB, $this->taskB] = app(WorkspaceDiscoveryService::class)->runAs($this->b, function () {
        $project = Project::factory()->forWorkspace($this->b)->create(['slug' => 'secret']);

        return [$project, Task::factory()->forProject($project)->create()];
    });
    app(WorkspaceDiscoveryService::class)->runAs($this->a,
        fn () => Project::factory()->forWorkspace($this->a)->create(['slug' => 'mine']));
    $this->actingAs($this->alice);
});

test('cannot open a project of another workspace through own workspace url', function () {
    $this->get('/alpha/projects/secret')->assertNotFound();
});

test('cannot open a task of another project through own project url', function () {
    $this->get("/alpha/projects/mine/tasks/{$this->taskB->id}")->assertNotFound();
});

test('cannot complete a task of another workspace', function () {
    $this->post("/alpha/projects/mine/tasks/{$this->taskB->id}/completion")->assertNotFound();
    $this->post("/beta/projects/secret/tasks/{$this->taskB->id}/completion")->assertForbidden();
});
