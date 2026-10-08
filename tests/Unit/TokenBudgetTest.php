<?php

use App\Domain\Prompt\Support\TokenBudget;

test('estimates tokens and trims to budget with a marker', function () {
    $budget = new TokenBudget(10);

    expect(TokenBudget::estimate('abcdefgh'))->toBe(2)
        ->and($budget->trim('short'))->toBe('short')
        ->and($trimmed = $budget->trim(str_repeat('x', 100)))->toHaveLength(40)
        ->and($trimmed)->toEndWith(TokenBudget::TRUNCATION_MARKER);
});
