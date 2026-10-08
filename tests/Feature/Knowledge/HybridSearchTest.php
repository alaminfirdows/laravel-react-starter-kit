<?php

use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\KnowledgeChunk;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Knowledge\Queries\HybridSearch;
use App\Domain\Project\Models\Project;
use Laravel\Ai\Embeddings;

/**
 * @return list<float>
 */
function axis(int $index): array
{
    $vector = array_fill(0, KnowledgeChunk::DIMENSIONS, 0.0);
    $vector[$index] = 1.0;

    return $vector;
}

function chunkFor(Project $project, string $content, ?array $embedding, DocType $type = DocType::Research, DocStatus $status = DocStatus::Draft): KnowledgeChunk
{
    $document = KnowledgeDocument::factory()->forProject($project)->create(['doc_type' => $type, 'status' => $status, 'title' => $content]);

    return KnowledgeChunk::withoutWorkspaceScope()->create([
        'workspace_id' => $project->workspace_id,
        'project_id' => $project->id,
        'document_id' => $document->id,
        'chunk_index' => 0,
        'content' => $content,
        'token_count' => 5,
        'embedding' => $embedding,
    ]);
}

beforeEach(function () {
    $this->project = Project::factory()->create();
    Embeddings::fake(fn () => [axis(0)]);
});

test('chunk matching both vector and text ranks first', function () {
    $vectorOnly = chunkFor($this->project, 'customers who buy', axis(0));
    $both = chunkFor($this->project, 'pricing for agencies', axis(0));
    $textOnly = chunkFor($this->project, 'pricing page copy', axis(5));
    chunkFor($this->project, 'unrelated hiring notes', axis(7));

    $results = app(HybridSearch::class)->handle($this->project, 'pricing');

    expect($results[0]->chunkId)->toBe($both->id)
        ->and(array_map(fn ($result) => $result->chunkId, $results))->toContain($vectorOnly->id, $textOnly->id)
        ->and($results)->toHaveCount(3);
});

test('never returns chunks of another project', function () {
    chunkFor(Project::factory()->create(), 'pricing secrets', axis(0));
    $own = chunkFor($this->project, 'pricing plan', axis(0));

    $results = app(HybridSearch::class)->handle($this->project, 'pricing');

    expect(array_map(fn ($result) => $result->chunkId, $results))->toBe([$own->id]);
});

test('filters by doc type and skips archived documents', function () {
    $icp = chunkFor($this->project, 'pricing for icp', axis(0), DocType::Icp);
    chunkFor($this->project, 'pricing research', axis(0));
    chunkFor($this->project, 'pricing old icp', axis(0), DocType::Icp, DocStatus::Archived);

    $results = app(HybridSearch::class)->handle($this->project, 'pricing', docTypes: [DocType::Icp]);

    expect(array_map(fn ($result) => $result->chunkId, $results))->toBe([$icp->id]);
});

test('falls back to full text when the embedding provider fails', function () {
    Embeddings::fake(fn () => throw new RuntimeException('down'));
    $chunk = chunkFor($this->project, 'pricing tiers', null);

    $results = app(HybridSearch::class)->handle($this->project, 'pricing');

    expect($results)->toHaveCount(1)
        ->and($results[0]->chunkId)->toBe($chunk->id);
});
