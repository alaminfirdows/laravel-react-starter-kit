<?php

use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\Decision;
use App\Domain\Knowledge\Models\Interview;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Servers\FounderServer;
use App\Mcp\Tools\GetDocumentTool;
use App\Mcp\Tools\ListDocumentsTool;
use App\Mcp\Tools\LogDecisionTool;
use App\Mcp\Tools\SaveKnowledgeTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Passport\Passport;

beforeEach(function () {
    Queue::fake();
    Embeddings::fake(fn () => [array_fill(0, 1536, 0.1)]);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->withMember($this->user, WorkspaceRole::Member)->create();
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();

    Passport::actingAs($this->user, ['mcp:use', 'workspace:'.$this->workspace->id]);
});

test('save_knowledge creates a document and a new version on update', function () {
    FounderServer::tool(SaveKnowledgeTool::class, [
        'project_id' => $this->project->id,
        'doc_type' => DocType::Icp->value,
        'title' => 'ICP',
        'body_md' => "# ICP\n\nSolo founders selling B2B SaaS.",
    ])->assertOk()->assertSee('v1');

    $document = KnowledgeDocument::query()->sole();
    expect($document->source)->toBe(DocSource::ClaudeMcp)
        ->and($document->status)->toBe(DocStatus::Draft);

    FounderServer::tool(SaveKnowledgeTool::class, [
        'project_id' => $this->project->id,
        'document_id' => $document->id,
        'title' => 'ICP',
        'body_md' => "# ICP\n\nSolo founders and small agencies.",
        'status' => DocStatus::Approved->value,
    ])->assertOk()->assertSee('v2');

    expect($document->refresh()->version)->toBe(2)
        ->and($document->status)->toBe(DocStatus::Approved)
        ->and($document->versions()->count())->toBe(2);
});

test('search, list and get return project knowledge', function () {
    FounderServer::tool(SaveKnowledgeTool::class, [
        'project_id' => $this->project->id,
        'doc_type' => DocType::Research->value,
        'title' => 'Churn interviews',
        'body_md' => "## Findings\n\nCustomers churn because onboarding is slow.",
    ])->assertOk();
    $document = KnowledgeDocument::query()->sole();

    FounderServer::tool(SearchKnowledgeTool::class, ['project_id' => $this->project->id, 'query' => 'onboarding churn'])
        ->assertOk()->assertSee(['Churn interviews', $document->id, 'Findings']);

    FounderServer::tool(ListDocumentsTool::class, ['project_id' => $this->project->id])
        ->assertOk()->assertSee(['Churn interviews', 'research', 'v1']);

    FounderServer::tool(GetDocumentTool::class, ['document_id' => $document->id])
        ->assertOk()->assertSee('onboarding is slow');
});

test('documents of another workspace are not found', function () {
    $foreign = KnowledgeDocument::factory()->forProject(Project::factory()->create())->create();

    FounderServer::tool(GetDocumentTool::class, ['document_id' => $foreign->id])->assertHasErrors(['Document not found']);
    FounderServer::tool(SaveKnowledgeTool::class, [
        'project_id' => $this->project->id,
        'document_id' => $foreign->id,
        'title' => 'Hijack',
        'body_md' => 'x',
    ])->assertHasErrors(['Document not found']);

    expect($foreign->refresh()->title)->not->toBe('Hijack');
});

test('save_knowledge refuses a document that mirrors a research row', function () {
    $document = KnowledgeDocument::factory()->forProject($this->project)->create(['doc_type' => DocType::Interview, 'title' => 'Interview: Ana']);
    app(WorkspaceDiscoveryService::class)->runAs($this->workspace,
        fn () => Interview::factory()->forProject($this->project)->create(['knowledge_document_id' => $document->id]));

    FounderServer::tool(SaveKnowledgeTool::class, [
        'project_id' => $this->project->id,
        'document_id' => $document->id,
        'title' => 'Hijack',
        'body_md' => 'x',
    ])->assertHasErrors(['This document mirrors a research row. Edit the row instead.']);

    expect($document->refresh()->title)->toBe('Interview: Ana');
});

test('viewers cannot save knowledge or log decisions', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer]);
    Passport::actingAs($viewer, ['mcp:use', 'workspace:'.$this->workspace->id]);

    FounderServer::tool(SaveKnowledgeTool::class, [
        'project_id' => $this->project->id,
        'doc_type' => DocType::Meeting->value,
        'title' => 'Notes',
        'body_md' => 'x',
    ])->assertHasErrors();
    FounderServer::tool(LogDecisionTool::class, [
        'project_id' => $this->project->id,
        'title' => 'Monthly only',
        'decision_md' => 'x',
    ])->assertHasErrors();

    expect(KnowledgeDocument::withoutWorkspaceScope()->count())->toBe(0)
        ->and(Decision::withoutWorkspaceScope()->count())->toBe(0);
});

test('log_decision records a decision from claude', function () {
    FounderServer::tool(LogDecisionTool::class, [
        'project_id' => $this->project->id,
        'title' => 'Charge monthly only',
        'decision_md' => 'No annual plan at launch.',
        'alternatives' => ['Annual with discount'],
    ])->assertOk()->assertSee('recorded');

    $decision = Decision::withoutWorkspaceScope()->sole();
    expect($decision->source)->toBe(DocSource::ClaudeMcp)
        ->and($decision->owner_id)->toBe($this->user->id)
        ->and($decision->alternatives)->toBe(['Annual with discount']);
});
