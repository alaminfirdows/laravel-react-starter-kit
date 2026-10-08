<?php

use App\Domain\Project\Enums\BusinessModel;
use App\Domain\Project\Enums\ProjectPhase;

test('options list every phase with label and description', function () {
    expect(ProjectPhase::options())->toHaveCount(3)
        ->and(ProjectPhase::options()[0])->toBe([
            'value' => 'planning',
            'label' => 'Planning',
            'description' => ProjectPhase::Planning->description(),
        ]);
});

test('select options use value and headline label', function () {
    expect(BusinessModel::options()[0])->toBe(['value' => 'b2b_saas', 'label' => 'B2B SaaS']);
});
