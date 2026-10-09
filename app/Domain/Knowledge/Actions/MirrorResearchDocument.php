<?php

namespace App\Domain\Knowledge\Actions;

use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Data\DocumentData;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;

/**
 * Keeps the knowledge document that mirrors a research row (interview, competitor) in sync,
 * so the row is found by knowledge search. The same document is updated on edit (new version).
 */
class MirrorResearchDocument
{
    public function __construct(protected SaveDocument $save) {}

    public function save(Project $project, DocType $type, string $title, string $bodyMd, Actor $actor, ?KnowledgeDocument $document = null): KnowledgeDocument
    {
        $status = $document === null || $document->status === DocStatus::Archived
            ? DocStatus::Draft
            : $document->status;

        return $this->save->handle($project, new DocumentData(
            docType: $type,
            title: $title,
            bodyMd: $bodyMd,
            status: $status,
            changeNote: $document === null ? null : 'Research row updated',
        ), $actor, $document, mirroringResearch: true);
    }

    public function archive(Project $project, KnowledgeDocument $document, Actor $actor): void
    {
        if ($document->status === DocStatus::Archived) {
            return;
        }

        $this->save->handle($project, new DocumentData(
            docType: $document->doc_type,
            title: $document->title,
            bodyMd: $document->body_md,
            status: DocStatus::Archived,
        ), $actor, $document, mirroringResearch: true);
    }
}
