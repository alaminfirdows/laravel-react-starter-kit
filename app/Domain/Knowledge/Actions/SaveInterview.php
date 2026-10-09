<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Data\InterviewData;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\Interview;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an interview row and its mirrored `interview` knowledge document.
 */
class SaveInterview
{
    public function __construct(
        protected MirrorResearchDocument $mirror,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Project $project, InterviewData $data, Actor $actor, ?Interview $interview = null): Interview
    {
        return DB::transaction(function () use ($project, $data, $actor, $interview): Interview {
            $isNew = $interview === null;
            $interview ??= new Interview(['project_id' => $project->id, 'workspace_id' => $project->workspace_id]);
            $interview->fill($data->attributes());

            $document = $this->mirror->save($project, DocType::Interview, $data->title(), $data->toMarkdown(), $actor, $interview->knowledgeDocument);

            $interview->knowledgeDocument()->associate($document);
            $interview->save();

            $this->activity->record($isNew ? 'interview.created' : 'interview.updated', $interview, [
                'interview_id' => $interview->id,
                'document_id' => $document->id,
            ], $actor);

            return $interview;
        });
    }
}
