<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Models\Competitor;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a competitor row and archives its mirrored knowledge document.
 */
class DeleteCompetitor
{
    public function __construct(
        protected MirrorResearchDocument $mirror,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Competitor $competitor, Actor $actor): void
    {
        DB::transaction(function () use ($competitor, $actor): void {
            if ($competitor->knowledgeDocument !== null) {
                $this->mirror->archive($competitor->project, $competitor->knowledgeDocument, $actor);
            }

            $this->activity->record('competitor.deleted', $competitor, [
                'competitor_id' => $competitor->id,
                'name' => $competitor->name,
                'document_id' => $competitor->knowledge_document_id,
            ], $actor);

            $competitor->delete();
        });
    }
}
