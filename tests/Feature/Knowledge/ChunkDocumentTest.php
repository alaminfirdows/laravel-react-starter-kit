<?php

use App\Domain\Knowledge\Actions\ChunkDocument;

test('splits by headings and keeps the heading path', function () {
    $chunks = app(ChunkDocument::class)->split("Intro text\n\n# Market\n\nAgencies\n\n## Size\n\nTen thousand\n\n# Pricing\n\nMonthly");

    expect(array_map(fn ($chunk) => $chunk->headingPath, $chunks))->toBe([null, 'Market', 'Market > Size', 'Pricing'])
        ->and($chunks[2]->content)->toContain('Ten thousand');
});

test('headings inside code fences are not headings', function () {
    $chunks = app(ChunkDocument::class)->split("# Setup\n\n```bash\n# install\nnpm i\n```");

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->headingPath)->toBe('Setup');
});

test('very long document is chunked under the token limit with overlap', function () {
    $paragraph = str_repeat('founders validate ideas with customer interviews ', 40);
    $markdown = "# Research\n\n".implode("\n\n", array_fill(0, 120, $paragraph)).' '.str_repeat('x', 5000);

    expect(strlen($markdown))->toBeGreaterThan(200_000);

    $chunks = app(ChunkDocument::class)->split($markdown);

    expect(count($chunks))->toBeGreaterThan(100)
        ->and(max(array_map(fn ($chunk) => $chunk->tokenCount, $chunks)))->toBeLessThanOrEqual(ChunkDocument::MAX_TOKENS)
        ->and($chunks[0]->content)->toContain(substr($chunks[1]->content, 0, 60));
});
