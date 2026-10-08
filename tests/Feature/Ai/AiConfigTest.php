<?php

test('in-app agents default to anthropic models with a token budget', function () {
    expect(config('ai.default'))->toBe('anthropic')
        ->and(config('ai.models.default'))->toBe('claude-sonnet-5-5')
        ->and(config('ai.models.drafting'))->toBe('claude-opus-5-5')
        ->and(config('ai.monthly_token_budget'))->toBeInt()->toBeGreaterThan(0)
        ->and(config('ai.providers.anthropic.key'))->toBe(env('ANTHROPIC_API_KEY'));
});
