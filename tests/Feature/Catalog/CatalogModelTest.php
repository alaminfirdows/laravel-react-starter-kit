<?php

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;

test('catalog task tree and actions load in order', function () {
    $root = CatalogTask::factory()->create();
    $second = CatalogTask::factory()->childOf($root)->create(['sort_order' => 2]);
    $first = CatalogTask::factory()->childOf($root)->create(['sort_order' => 1]);

    expect($root->children->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($first->parent->is($root))->toBeTrue();
});

test('default pack is resolved per phase', function () {
    Pack::factory()->defaultFor(ProjectPhase::Selling)->create(['key' => 'gtm']);
    Pack::factory()->defaultFor(ProjectPhase::Planning)->create(['key' => 'plan']);
    Pack::factory()->create(['key' => 'extra', 'audience' => ['phase' => 'planning'], 'is_default' => false]);

    expect(Pack::defaultForPhase(ProjectPhase::Planning)?->key)->toBe('plan')
        ->and(Pack::defaultForPhase(ProjectPhase::Developing))->toBeNull();
});
