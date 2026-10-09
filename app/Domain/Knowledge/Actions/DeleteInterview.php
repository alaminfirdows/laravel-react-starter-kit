<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Models\Interview;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an interview row and archives its mirrored knowledge document.
 */
class DeleteInterview
{
    public function __construct(
        protected MirrorResearchDocument $mirror,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Interview $interview, Actor $actor): void
    {
        DB::transaction(function () use ($interview, $actor): void {
            if ($interview->knowledgeDocument !== null) {
                $this->mirror->archive($interview->project, $interview->knowledgeDocument, $actor);
            }

            $this->activity->record('interview.deleted', $interview, [
                'interview_id' => $interview->id,
                'person' => $interview->person,
                'document_id' => $interview->knowledge_document_id,
            ], $actor);

            $interview->delete();
        });
    }
}
