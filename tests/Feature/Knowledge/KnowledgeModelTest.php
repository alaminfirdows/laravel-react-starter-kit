<?php

use App\Domain\Knowledge\Models\Decision;
use App\Domain\Knowledge\Models\KnowledgeChunk;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();
});

test('documents and decisions are scoped to the current workspace', function () {
    KnowledgeDocument::factory()->forProject($this->project)->create();
    KnowledgeDocument::factory()->create();
    Decision::factory()->forProject($this->project)->create();
    Decision::factory()->create();

    expect(KnowledgeDocument::count())->toBe(1)
        ->and(Decision::count())->toBe(1)
        ->and($this->project->knowledgeDocuments()->count())->toBe(1);
});

test('invalid doc type is rejected by the database', function () {
    $document = KnowledgeDocument::factory()->forProject($this->project)->create();

    DB::table('knowledge_documents')->where('id', $document->id)->update(['doc_type' => 'novel']);
})->throws(QueryException::class);

test('chunk stores embedding and generates the tsvector', function () {
    $document = KnowledgeDocument::factory()->forProject($this->project)->create();
    $embedding = array_fill(0, KnowledgeChunk::DIMENSIONS, 0.0);
    $embedding[0] = 1.0;

    $chunk = $document->chunks()->create([
        'workspace_id' => $document->workspace_id,
        'project_id' => $document->project_id,
        'chunk_index' => 0,
        'heading_path' => 'Pricing',
        'content' => 'Founders pay monthly',
        'token_count' => 4,
        'embedding' => $embedding,
    ]);

    expect($chunk->fresh()->embedding)->toHaveCount(KnowledgeChunk::DIMENSIONS)
        ->and(DB::table('knowledge_chunks')->where('id', $chunk->id)->whereRaw("tsv @@ plainto_tsquery('english', 'founder pricing')")->exists())->toBeTrue();
});
