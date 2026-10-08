<?php

namespace App\Domain\Knowledge\Queries;

use App\Domain\Knowledge\Data\SearchResultData;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\KnowledgeChunk;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

/**
 * Project-scoped search over knowledge chunks: vector and full-text ranks merged with
 * reciprocal rank fusion. Archived documents are left out. When the embedding provider fails,
 * results come from full text only.
 */
class HybridSearch
{
    public const int RRF_K = 60;

    public const float MIN_SIMILARITY = 0.3;

    protected const int CANDIDATES_PER_RESULT = 4;

    /**
     * @param  list<DocType>  $docTypes
     * @return list<SearchResultData>
     */
    public function handle(Project $project, string $query, int $limit = 8, array $docTypes = []): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $pool = $limit * self::CANDIDATES_PER_RESULT;
        $scores = [];

        foreach ([$this->vectorRanks($project, $query, $docTypes, $pool), $this->textRanks($project, $query, $docTypes, $pool)] as $ranked) {
            foreach ($ranked as $rank => $chunkId) {
                $scores[$chunkId] = ($scores[$chunkId] ?? 0) + 1 / (self::RRF_K + $rank + 1);
            }
        }

        arsort($scores);
        $scores = array_slice($scores, 0, $limit, true);

        $chunks = $this->chunks($project, $docTypes)
            ->whereIn('knowledge_chunks.id', array_keys($scores))
            ->get(['knowledge_chunks.*', 'documents.title as document_title', 'documents.doc_type'])
            ->keyBy('id');

        $results = [];

        foreach ($scores as $chunkId => $score) {
            $chunk = $chunks->get($chunkId);

            if ($chunk !== null) {
                $results[] = new SearchResultData(
                    chunkId: $chunk->id,
                    documentId: $chunk->document_id,
                    documentTitle: (string) $chunk->getAttribute('document_title'),
                    docType: DocType::from((string) $chunk->getAttribute('doc_type')),
                    headingPath: $chunk->heading_path,
                    content: $chunk->content,
                    score: round($score, 6),
                );
            }
        }

        return $results;
    }

    /**
     * @param  list<DocType>  $docTypes
     * @return list<string>
     */
    protected function vectorRanks(Project $project, string $query, array $docTypes, int $pool): array
    {
        try {
            $vector = Embeddings::for([$query])->dimensions(KnowledgeChunk::DIMENSIONS)->cache()->generate()->first();
        } catch (Throwable $exception) {
            Log::warning('Knowledge search fell back to full text.', ['error' => $exception->getMessage()]);

            return [];
        }

        return $this->chunks($project, $docTypes)
            ->whereNotNull('knowledge_chunks.embedding')
            ->whereVectorSimilarTo('knowledge_chunks.embedding', $vector, self::MIN_SIMILARITY)
            ->limit($pool)
            ->get(['knowledge_chunks.id'])
            ->map(fn (KnowledgeChunk $chunk): string => $chunk->id)
            ->values()
            ->all();
    }

    /**
     * @param  list<DocType>  $docTypes
     * @return list<string>
     */
    protected function textRanks(Project $project, string $query, array $docTypes, int $pool): array
    {
        return $this->chunks($project, $docTypes)
            ->whereRaw("knowledge_chunks.tsv @@ websearch_to_tsquery('english', ?)", [$query])
            ->orderByRaw("ts_rank(knowledge_chunks.tsv, websearch_to_tsquery('english', ?)) desc", [$query])
            ->limit($pool)
            ->get(['knowledge_chunks.id'])
            ->map(fn (KnowledgeChunk $chunk): string => $chunk->id)
            ->values()
            ->all();
    }

    /**
     * @param  list<DocType>  $docTypes
     * @return Builder<KnowledgeChunk>
     */
    protected function chunks(Project $project, array $docTypes): Builder
    {
        return KnowledgeChunk::withoutWorkspaceScope()
            ->join('knowledge_documents as documents', 'documents.id', '=', 'knowledge_chunks.document_id')
            ->where('knowledge_chunks.workspace_id', $project->workspace_id)
            ->where('knowledge_chunks.project_id', $project->id)
            ->where('documents.status', '!=', DocStatus::Archived)
            ->when($docTypes !== [], fn (Builder $chunks) => $chunks->whereIn('documents.doc_type', $docTypes));
    }
}
