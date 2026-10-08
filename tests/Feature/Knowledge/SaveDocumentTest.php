<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Actions\SaveDocument;
use App\Domain\Knowledge\Data\DocumentData;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Jobs\EmbedDocumentJob;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->workspace = Workspace::factory()->create();
    app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($this->workspace);
    $this->project = Project::factory()->forWorkspace($this->workspace)->create();
    $this->actor = Actor::system();
});

function saveDoc(Project $project, string $body, DocType $type = DocType::Research, DocStatus $status = DocStatus::Draft, ?KnowledgeDocument $document = null): KnowledgeDocument
{
    return app(SaveDocument::class)->handle($project, new DocumentData($type, 'Doc', $body, $status), Actor::system(), $document);
}

test('new document gets version 1, chunks and an embed job', function () {
    $document = saveDoc($this->project, "# Market\n\nSmall agencies.");

    expect($document->version)->toBe(1)
        ->and($document->versions()->count())->toBe(1)
        ->and($document->chunks()->count())->toBe(1);
    Queue::assertPushed(EmbedDocumentJob::class, fn (EmbedDocumentJob $job): bool => $job->documentId === $document->id && $job->version === 1);
    $this->assertDatabaseHas('activity_log', ['event' => 'knowledge.saved', 'subject_id' => $document->id]);
});

test('saving the same body twice adds no version and no embed job', function () {
    $document = saveDoc($this->project, 'Same body');
    Queue::fake();

    $document = saveDoc($this->project, 'Same body', status: DocStatus::Approved, document: $document);

    expect($document->version)->toBe(1)
        ->and($document->status)->toBe(DocStatus::Approved)
        ->and($document->versions()->count())->toBe(1);
    Queue::assertNotPushed(EmbedDocumentJob::class);
});

test('changed body bumps the version and re-chunks', function () {
    $document = saveDoc($this->project, 'First');

    $document = saveDoc($this->project, "First\n\n## New\n\nSecond", document: $document);

    expect($document->version)->toBe(2)
        ->and($document->versions()->pluck('version')->all())->toBe([1, 2])
        ->and($document->chunks()->pluck('heading_path')->all())->toBe([null, 'New']);
});

test('approving a second singleton archives the previous approved one', function () {
    $first = saveDoc($this->project, 'ICP one', DocType::Icp, DocStatus::Approved);
    $otherProject = saveDoc(Project::factory()->forWorkspace($this->workspace)->create(), 'Other ICP', DocType::Icp, DocStatus::Approved);

    $second = saveDoc($this->project, 'ICP two', DocType::Icp, DocStatus::Approved);

    expect($first->fresh()->status)->toBe(DocStatus::Archived)
        ->and($second->status)->toBe(DocStatus::Approved)
        ->and($otherProject->fresh()->status)->toBe(DocStatus::Approved);
});

test('non singleton types keep several approved documents', function () {
    $first = saveDoc($this->project, 'Interview A', DocType::Interview, DocStatus::Approved);
    saveDoc($this->project, 'Interview B', DocType::Interview, DocStatus::Approved);

    expect($first->fresh()->status)->toBe(DocStatus::Approved);
});
