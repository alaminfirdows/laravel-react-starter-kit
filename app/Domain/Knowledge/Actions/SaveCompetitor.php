<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Data\CompetitorData;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\Competitor;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a competitor row and its mirrored `competitor` knowledge document.
 */
class SaveCompetitor
{
    public function __construct(
        protected MirrorResearchDocument $mirror,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Project $project, CompetitorData $data, Actor $actor, ?Competitor $competitor = null): Competitor
    {
        return DB::transaction(function () use ($project, $data, $actor, $competitor): Competitor {
            $isNew = $competitor === null;
            $competitor ??= new Competitor(['project_id' => $project->id, 'workspace_id' => $project->workspace_id]);
            $competitor->fill($data->attributes());

            $document = $this->mirror->save($project, DocType::Competitor, $data->title(), $data->toMarkdown(), $actor, $competitor->knowledgeDocument);

            $competitor->knowledgeDocument()->associate($document);
            $competitor->save();

            $this->activity->record($isNew ? 'competitor.created' : 'competitor.updated', $competitor, [
                'competitor_id' => $competitor->id,
                'document_id' => $document->id,
            ], $actor);

            return $competitor;
        });
    }
}
