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

    public function update(User $user, KnowledgeDocument $document): bool
    {
        return $user->can('update', $document->project);
    }
}
