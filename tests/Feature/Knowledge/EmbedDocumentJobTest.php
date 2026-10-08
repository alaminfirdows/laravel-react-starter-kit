<?php

use App\Domain\Knowledge\Actions\ChunkDocument;
use App\Domain\Knowledge\Jobs\EmbedDocumentJob;
use App\Domain\Knowledge\Models\KnowledgeChunk;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    $this->document = KnowledgeDocument::factory()->create(['body_md' => "# One\n\nAlpha\n\n# Two\n\nBeta pricing"]);
    app(ChunkDocument::class)->handle($this->document);
});

test('embeds every chunk and marks the document embedded', function () {
    Embeddings::fake();

    (new EmbedDocumentJob($this->document->id, 1))->handle();

    expect(KnowledgeChunk::withoutWorkspaceScope()->whereNull('embedding')->count())->toBe(0)
        ->and($this->document->fresh()->embedded_at)->not->toBeNull();
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->inputs === ["# One\n\nAlpha", "# Two\n\nBeta pricing"] && $prompt->dimensions === KnowledgeChunk::DIMENSIONS);
});

test('stale version is skipped', function () {
    Embeddings::fake();

    (new EmbedDocumentJob($this->document->id, 2))->handle();

    Embeddings::assertNothingGenerated();
});

test('provider failure throws for retry and keeps full text search', function () {
    Embeddings::fake(fn () => throw new RuntimeException('provider down'));

    expect(fn () => (new EmbedDocumentJob($this->document->id, 1))->handle())->toThrow(RuntimeException::class)
        ->and($this->document->fresh()->embedded_at)->toBeNull()
        ->and(DB::table('knowledge_chunks')->whereRaw("tsv @@ plainto_tsquery('english', 'pricing')")->count())->toBe(1)
        ->and((new EmbedDocumentJob($this->document->id, 1))->tries)->toBeGreaterThan(1);
});
