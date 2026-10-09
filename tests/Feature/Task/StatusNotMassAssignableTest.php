<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

test('status is ignored by mass assignment', function (string $model) {
    /** @var Model $instance */
    $instance = new $model(['status' => 'hacked']);

    expect($instance->getAttributes()['status'] ?? null)->not->toBe('hacked');
})->with([
    Workspace::class,
    CatalogTask::class,
    Pack::class,
    KnowledgeDocument::class,
    Approval::class,
    ActionRun::class,
    Task::class,
]);
