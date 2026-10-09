<?php

namespace App\Domain\Knowledge\Policies;

use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Models\User;

class KnowledgeDocumentPolicy
{
    public function view(User $user, KnowledgeDocument $document): bool
    {
        return $user->can('view', $document->project);
    }

    /**
     * Documents mirroring an interview or competitor are read-only here; edit the research row instead.
     */
    public function update(User $user, KnowledgeDocument $document): bool
    {
        return $user->can('update', $document->project) && ! $document->isResearchMirror();
    }
}
