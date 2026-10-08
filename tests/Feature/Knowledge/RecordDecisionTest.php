<?php

use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Actions\RecordDecision;
use App\Domain\Knowledge\Data\DecisionData;
use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Project\Jobs\RebuildContextSnapshotJob;
use App\Domain\Project\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('records a decision with owner, activity and snapshot rebuild', function () {
    Queue::fake();
    $project = Project::factory()->create();
    $user = User::factory()->create();

    $decision = app(RecordDecision::class)->handle($project, new DecisionData(
        title: 'Charge monthly',
        decisionMd: 'Monthly plans only.',
        alternatives: ['Annual only'],
        source: DocSource::ClaudeMcp,
    ), Actor::agent($user, 'Claude'));

    expect($decision->owner_id)->toBe($user->id)
        ->and($decision->decided_on->isToday())->toBeTrue()
        ->and($decision->alternatives)->toBe(['Annual only'])
        ->and($decision->source)->toBe(DocSource::ClaudeMcp);
    $this->assertDatabaseHas('activity_log', ['event' => 'decision.recorded', 'subject_id' => $decision->id, 'client_name' => 'Claude']);
    Queue::assertPushed(RebuildContextSnapshotJob::class, fn (RebuildContextSnapshotJob $job): bool => $job->projectId === $project->id);
});
