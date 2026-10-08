<?php

use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\Decision;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    Queue::fake();
    Embeddings::fake(fn () => [array_fill(0, 1536, 0.1)]);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);
    $this->actingAs($this->user);
});

function knowledgeDocument(Workspace $workspace, Project $project, array $attributes = []): KnowledgeDocument
{
    return app(WorkspaceDiscoveryService::class)->runAs($workspace,
        fn () => KnowledgeDocument::factory()->forProject($project)->create($attributes));
}

test('create a document, then update it as a new version', function () {
    $this->get('/acme/projects/rocket/knowledge/create?type=icp')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/knowledge/edit')
            ->where('document', null)
            ->where('defaultType', 'icp')
            ->has('options.docTypes', count(DocType::cases())));

    $this->post('/acme/projects/rocket/knowledge', [
        'doc_type' => 'icp',
        'title' => 'ICP',
        'body_md' => "# ICP\n\nSolo founders.",
        'status' => 'draft',
    ])->assertRedirect();

    $document = KnowledgeDocument::withoutWorkspaceScope()->sole();

    $this->put("/acme/projects/rocket/knowledge/{$document->id}", [
        'title' => 'ICP v2',
        'body_md' => "# ICP\n\nSolo founders and agencies.",
        'status' => 'approved',
        'change_note' => 'Added agencies',
    ])->assertRedirect("/acme/projects/rocket/knowledge/{$document->id}");

    expect($document->refresh()->version)->toBe(2)
        ->and($document->status)->toBe(DocStatus::Approved)
        ->and($document->doc_type)->toBe(DocType::Icp);

    $this->get("/acme/projects/rocket/knowledge/{$document->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/knowledge/show')
            ->where('document.title', 'ICP v2')
            ->where('document.bodyMd', "# ICP\n\nSolo founders and agencies.")
            ->has('document.versions', 2)
            ->where('document.versions.0.changeNote', 'Added agencies'));
});

test('index lists documents and loads search results only on demand', function () {
    knowledgeDocument($this->workspace, $this->project, ['title' => 'Positioning', 'doc_type' => DocType::Positioning]);

    $this->get('/acme/projects/rocket/knowledge?q=churn')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/knowledge/index')
            ->has('documents', 1)
            ->where('documents.0.title', 'Positioning')
            ->missing('documents.0.bodyMd')
            ->where('query', 'churn')
            ->missing('results')
            ->reloadOnly('results', fn (Assert $reload) => $reload->has('results')));
});

test('documents of another project are not found', function () {
    $other = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'other']);
    $document = knowledgeDocument($this->workspace, $other);

    $this->get("/acme/projects/rocket/knowledge/{$document->id}")->assertNotFound();
});

test('viewers can read knowledge but not write', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer]);
    $document = knowledgeDocument($this->workspace, $this->project);
    $this->actingAs($viewer);

    $this->get("/acme/projects/rocket/knowledge/{$document->id}")->assertOk();
    $this->get("/acme/projects/rocket/knowledge/{$document->id}/edit")->assertForbidden();
    $this->post('/acme/projects/rocket/knowledge', ['doc_type' => 'icp', 'title' => 'x', 'body_md' => 'x', 'status' => 'draft'])->assertForbidden();
    $this->post('/acme/projects/rocket/decisions', ['title' => 'x', 'decision_md' => 'x'])->assertForbidden();
});

test('record and list decisions', function () {
    $this->post('/acme/projects/rocket/decisions', [
        'title' => 'Charge monthly only',
        'decision_md' => 'No annual plan at launch.',
        'rationale_md' => 'Keep it simple.',
    ])->assertRedirect();

    expect(Decision::withoutWorkspaceScope()->sole()->owner_id)->toBe($this->user->id);

    $this->get('/acme/projects/rocket/decisions')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/decisions/index')
            ->has('decisions', 1)
            ->where('decisions.0.title', 'Charge monthly only')
            ->where('decisions.0.owner', $this->user->name));
});
