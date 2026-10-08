<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Data\DocumentData;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Jobs\EmbedDocumentJob;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a knowledge document. A changed body adds a version and re-chunks;
 * the same body only updates metadata. Approving a singleton type archives the previous one.
 */
class SaveDocument
{
    public function __construct(
        protected ChunkDocument $chunk,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Project $project, DocumentData $data, Actor $actor, ?KnowledgeDocument $document = null): KnowledgeDocument
    {
        $checksum = KnowledgeDocument::checksumFor($data->bodyMd);
        $bodyChanged = $document === null || $document->checksum !== $checksum;

        $document = DB::transaction(function () use ($project, $data, $actor, $document, $checksum, $bodyChanged): KnowledgeDocument {
            $document ??= new KnowledgeDocument([
                'project_id' => $project->id,
                'workspace_id' => $project->workspace_id,
                'doc_type' => $data->docType,
                'version' => 0,
            ]);

            $document->fill([
                'title' => $data->title,
                'status' => $data->status,
                'source' => $data->source,
                'task_id' => $data->taskId ?? $document->task_id,
                'tags' => $data->tags ?? $document->tags,
            ]);

            if ($bodyChanged) {
                $document->fill([
                    'body_md' => $data->bodyMd,
                    'checksum' => $checksum,
                    'version' => $document->version + 1,
                    'embedded_at' => null,
                ]);
            }

            $document->save();

            if ($bodyChanged) {
                $document->versions()->create([
                    'version' => $document->version,
                    'body_md' => $data->bodyMd,
                    'checksum' => $checksum,
                    'created_by_type' => $actor->type,
                    'created_by_id' => $actor->id,
                    'change_note' => $data->changeNote,
                ]);

                $this->chunk->handle($document);
            }

            if ($document->status === DocStatus::Approved && $document->doc_type->isSingleton()) {
                $this->archiveOtherApproved($document);
            }

            $this->activity->record('knowledge.saved', $document, [
                'document_id' => $document->id,
                'version' => $document->version,
                'body_changed' => $bodyChanged,
            ], $actor);

            return $document;
        });

        if ($bodyChanged) {
            EmbedDocumentJob::dispatch($document->id, $document->version)->afterCommit();
        }

        return $document;
    }

    protected function archiveOtherApproved(KnowledgeDocument $document): void
    {
        KnowledgeDocument::withoutWorkspaceScope()
            ->where('project_id', $document->project_id)
            ->where('doc_type', $document->doc_type)
            ->where('status', DocStatus::Approved)
            ->whereKeyNot($document->id)
            ->update(['status' => DocStatus::Archived]);
    }
}
