<?php

use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\Decision;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Actions\BuildContextSnapshot;
use App\Domain\Project\Jobs\RebuildContextSnapshotJob;
use App\Domain\Project\Models\Project;

beforeEach(function () {
    $this->project = Project::factory()->create(['one_liner' => 'CRM for dentists', 'goals' => [['title' => '10 customers', 'metric' => 'MRR', 'target' => '5k', 'due' => '2027-01-01'], ['title' => 'Launch']]]);
});

test('snapshot has profile, approved singleton docs and recent decisions', function () {
    $icp = KnowledgeDocument::factory()->forProject($this->project)->approved()->create(['doc_type' => DocType::Icp, 'title' => 'Our ICP', 'body_md' => "# ICP\n\nSmall dental clinics in Germany."]);
    KnowledgeDocument::factory()->forProject($this->project)->create(['doc_type' => DocType::Positioning, 'title' => 'Draft positioning', 'status' => DocStatus::Draft]);
    KnowledgeDocument::factory()->forProject($this->project)->approved()->create(['doc_type' => DocType::Interview, 'title' => 'Interview Anna']);
    $decision = Decision::factory()->forProject($this->project)->create(['title' => 'Charge monthly']);

    $snapshot = app(BuildContextSnapshot::class)->handle($this->project);

    expect($snapshot)
        ->toContain('CRM for dentists')
        ->toContain("- 10 customers (MRR 5k, due 2027-01-01)\n- Launch")
        ->toContain("### ICP: Our ICP (`{$icp->id}`)")
        ->toContain('Small dental clinics in Germany.')
        ->toContain("Charge monthly (`{$decision->id}`)")
        ->not->toContain('Draft positioning')
        ->not->toContain('Interview Anna')
        ->and($this->project->fresh()->context_snapshot_md)->toBe($snapshot);
});

test('snapshot stays within the size limit', function () {
    KnowledgeDocument::factory()->forProject($this->project)->approved()->create(['doc_type' => DocType::Brief, 'body_md' => str_repeat('word ', 5000)]);
    Decision::factory()->forProject($this->project)->count(15)->create(['title' => str_repeat('Long decision title ', 10)]);

    expect(mb_strlen(app(BuildContextSnapshot::class)->handle($this->project)))->toBeLessThanOrEqual(BuildContextSnapshot::MAX_CHARS);
});

test('job rebuilds the snapshot', function () {
    (new RebuildContextSnapshotJob($this->project->id))->handle(app(BuildContextSnapshot::class));

    expect($this->project->fresh()->context_snapshot_md)->toContain('CRM for dentists');
});
