<?php

namespace App\Domain\Knowledge\Jobs;

use App\Domain\Knowledge\Models\KnowledgeChunk;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Embeddings;

/**
 * Embeds the chunks of one document version. A stale version (body saved again) is skipped:
 * the newer save dispatches its own job. Failures retry; full-text search works meanwhile.
 */
class EmbedDocumentJob implements ShouldQueue
{
    use Queueable;

    public const int BATCH_SIZE = 64;

    public int $tries = 5;

    public function __construct(public string $documentId, public int $version) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(): void
    {
        $document = KnowledgeDocument::withoutWorkspaceScope()->find($this->documentId);

        if ($document === null || $document->version !== $this->version) {
            return;
        }

        KnowledgeChunk::withoutWorkspaceScope()
            ->where('document_id', $document->id)
            ->whereNull('embedding')
            ->chunkById(self::BATCH_SIZE, function ($chunks): void {
                $response = Embeddings::for($chunks->pluck('content')->all())
                    ->dimensions(KnowledgeChunk::DIMENSIONS)
                    ->generate();

                foreach ($chunks->values() as $position => $chunk) {
                    $chunk->update(['embedding' => $response->embeddings[$position]]);
                }
            });

        $document->forceFill(['embedded_at' => now()])->saveQuietly();
    }
}
