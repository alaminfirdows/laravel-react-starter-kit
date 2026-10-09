<?php

use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\Competitor;
use App\Domain\Knowledge\Models\Interview;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->ownedBy($this->user)->create(['slug' => 'acme']);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'rocket']);
    $this->actingAs($this->user);
});

function researchInterview(Workspace $workspace, Project $project, array $attributes = []): Interview
{
    return app(WorkspaceDiscoveryService::class)->runAs($workspace,
        fn () => Interview::factory()->forProject($project)->create($attributes));
}

function researchCompetitor(Workspace $workspace, Project $project, array $attributes = []): Competitor
{
    return app(WorkspaceDiscoveryService::class)->runAs($workspace,
        fn () => Competitor::factory()->forProject($project)->create($attributes));
}

test('create, edit and delete an interview keeps one mirrored document', function () {
    $this->post('/acme/projects/rocket/research/interviews', [
        'person' => 'Ana Lopez',
        'company' => 'Acme Agency',
        'role' => 'Founder',
        'interviewed_on' => '2026-10-01',
        'pain' => 'Invoices take a full day each month.',
        'quotes' => '"I hate Fridays."',
    ])->assertRedirect();

    $interview = Interview::withoutWorkspaceScope()->sole();
    $document = KnowledgeDocument::withoutWorkspaceScope()->sole();

    expect($interview->knowledge_document_id)->toBe($document->id)
        ->and($interview->interviewed_on->toDateString())->toBe('2026-10-01')
        ->and($document->doc_type)->toBe(DocType::Interview)
        ->and($document->title)->toBe('Interview: Ana Lopez (Acme Agency)')
        ->and($document->body_md)->toContain("## Pain\n\nInvoices take a full day each month.")
        ->and($document->version)->toBe(1);
    $this->assertDatabaseHas('activity_log', ['event' => 'interview.created', 'subject_id' => $interview->id]);

    $this->put("/acme/projects/rocket/research/interviews/{$interview->id}", [
        'person' => 'Ana Lopez',
        'pain' => 'Invoices take two days.',
    ])->assertRedirect();

    expect(KnowledgeDocument::withoutWorkspaceScope()->count())->toBe(1)
        ->and($document->refresh()->version)->toBe(2)
        ->and($document->versions()->count())->toBe(2)
        ->and($document->body_md)->toContain('Invoices take two days.')
        ->and($interview->refresh()->company)->toBeNull();
    $this->assertDatabaseHas('activity_log', ['event' => 'interview.updated', 'subject_id' => $interview->id]);

    $this->delete("/acme/projects/rocket/research/interviews/{$interview->id}")->assertRedirect();

    expect(Interview::withoutWorkspaceScope()->count())->toBe(0)
        ->and($document->refresh()->status)->toBe(DocStatus::Archived);
    $this->assertDatabaseHas('activity_log', ['event' => 'interview.deleted', 'subject_id' => $interview->id]);
});

test('create, edit and delete a competitor keeps one mirrored document', function () {
    $this->post('/acme/projects/rocket/research/competitors', [
        'name' => 'Ledgerly',
        'url' => 'https://ledgerly.example',
        'pricing' => '$29/mo',
        'last_reviewed_at' => '2026-10-05',
    ])->assertRedirect();

    $competitor = Competitor::withoutWorkspaceScope()->sole();
    $document = KnowledgeDocument::withoutWorkspaceScope()->sole();

    expect($competitor->knowledge_document_id)->toBe($document->id)
        ->and($document->doc_type)->toBe(DocType::Competitor)
        ->and($document->title)->toBe('Competitor: Ledgerly')
        ->and($document->body_md)->toContain("## Pricing\n\n\$29/mo");
    $this->assertDatabaseHas('activity_log', ['event' => 'competitor.created', 'subject_id' => $competitor->id]);

    $this->put("/acme/projects/rocket/research/competitors/{$competitor->id}", [
        'name' => 'Ledgerly',
        'pricing' => '$39/mo',
    ])->assertRedirect();

    expect(KnowledgeDocument::withoutWorkspaceScope()->count())->toBe(1)
        ->and($document->refresh()->version)->toBe(2)
        ->and($document->body_md)->toContain('$39/mo');
    $this->assertDatabaseHas('activity_log', ['event' => 'competitor.updated', 'subject_id' => $competitor->id]);

    $this->delete("/acme/projects/rocket/research/competitors/{$competitor->id}")->assertRedirect();

    expect(Competitor::withoutWorkspaceScope()->count())->toBe(0)
        ->and($document->refresh()->status)->toBe(DocStatus::Archived);
    $this->assertDatabaseHas('activity_log', ['event' => 'competitor.deleted', 'subject_id' => $competitor->id]);
});

test('saving the same fields again adds no document version', function () {
    $this->post('/acme/projects/rocket/research/competitors', ['name' => 'Ledgerly'])->assertRedirect();
    $competitor = Competitor::withoutWorkspaceScope()->sole();

    $this->put("/acme/projects/rocket/research/competitors/{$competitor->id}", ['name' => 'Ledgerly'])->assertRedirect();

    expect(KnowledgeDocument::withoutWorkspaceScope()->sole()->version)->toBe(1);
});

test('index lists the interviews and competitors of the project only', function () {
    $other = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'other']);
    researchInterview($this->workspace, $this->project, ['person' => 'Ana']);
    researchInterview($this->workspace, $other, ['person' => 'Ben']);
    researchCompetitor($this->workspace, $this->project, ['name' => 'Ledgerly']);
    researchCompetitor($this->workspace, $other, ['name' => 'Other Co']);

    $this->get('/acme/projects/rocket/research')
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/research/index')
            ->has('interviews', 1)
            ->where('interviews.0.person', 'Ana')
            ->has('competitors', 1)
            ->where('competitors.0.name', 'Ledgerly'));
});

test('research rows of another project cannot be edited or deleted', function () {
    $other = Project::factory()->forWorkspace($this->workspace)->create(['slug' => 'other']);
    $interview = researchInterview($this->workspace, $other);
    $competitor = researchCompetitor($this->workspace, $other);

    $this->put("/acme/projects/rocket/research/interviews/{$interview->id}", ['person' => 'Hijack'])->assertNotFound();
    $this->delete("/acme/projects/rocket/research/interviews/{$interview->id}")->assertNotFound();
    $this->put("/acme/projects/rocket/research/competitors/{$competitor->id}", ['name' => 'Hijack'])->assertNotFound();
    $this->delete("/acme/projects/rocket/research/competitors/{$competitor->id}")->assertNotFound();

    expect($interview->refresh()->person)->not->toBe('Hijack')
        ->and($competitor->refresh()->name)->not->toBe('Hijack');
    $this->assertDatabaseCount('activity_log', 0);
});

test('viewers can read research but not write', function () {
    $viewer = User::factory()->create();
    $this->workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer]);
    $interview = researchInterview($this->workspace, $this->project);
    $competitor = researchCompetitor($this->workspace, $this->project);
    $this->actingAs($viewer);

    $this->get('/acme/projects/rocket/research')->assertOk();
    $this->post('/acme/projects/rocket/research/interviews', ['person' => 'x'])->assertForbidden();
    $this->put("/acme/projects/rocket/research/interviews/{$interview->id}", ['person' => 'x'])->assertForbidden();
    $this->delete("/acme/projects/rocket/research/interviews/{$interview->id}")->assertForbidden();
    $this->post('/acme/projects/rocket/research/competitors', ['name' => 'x'])->assertForbidden();
    $this->put("/acme/projects/rocket/research/competitors/{$competitor->id}", ['name' => 'x'])->assertForbidden();
    $this->delete("/acme/projects/rocket/research/competitors/{$competitor->id}")->assertForbidden();

    expect(Interview::withoutWorkspaceScope()->count())->toBe(1)
        ->and(Competitor::withoutWorkspaceScope()->count())->toBe(1);
});

test('person and name are required and url must be valid', function () {
    $this->post('/acme/projects/rocket/research/interviews', [])->assertSessionHasErrors('person');
    $this->post('/acme/projects/rocket/research/competitors', ['url' => 'not a url'])->assertSessionHasErrors(['name', 'url']);
});

test('mirrored research documents cannot be edited directly but the row still updates them', function () {
    $this->post('/acme/projects/rocket/research/interviews', ['person' => 'Ana Lopez', 'pain' => 'Slow invoices.'])->assertRedirect();
    $interview = Interview::withoutWorkspaceScope()->sole();
    $document = KnowledgeDocument::withoutWorkspaceScope()->sole();
    $payload = ['title' => 'Edited', 'body_md' => 'Direct edit', 'status' => 'draft'];

    $this->get("/acme/projects/rocket/knowledge/{$document->id}/edit")->assertForbidden();
    $this->put("/acme/projects/rocket/knowledge/{$document->id}", $payload)->assertForbidden();
    $this->get("/acme/projects/rocket/knowledge/{$document->id}")
        ->assertInertia(fn (Assert $page) => $page->where('canEditDocument', false));

    $this->put("/acme/projects/rocket/research/interviews/{$interview->id}", ['person' => 'Ana Lopez', 'pain' => 'Slower invoices.'])->assertRedirect();

    expect($document->refresh()->version)->toBe(2)
        ->and($document->body_md)->toContain('Slower invoices.');
});

test('research dates must use Y-m-d', function () {
    $this->post('/acme/projects/rocket/research/interviews', ['person' => 'Ana', 'interviewed_on' => '10/01/2026'])->assertSessionHasErrors('interviewed_on');
    $this->post('/acme/projects/rocket/research/competitors', ['name' => 'Rival', 'last_reviewed_at' => 'yesterday'])->assertSessionHasErrors('last_reviewed_at');
});
