<?php

namespace App\Domain\Knowledge\Policies;

use App\Domain\Knowledge\Models\Competitor;
use App\Models\User;

/**
 * Viewers read, editors write (same as knowledge documents).
 */
class CompetitorPolicy
{
    public function view(User $user, Competitor $competitor): bool
    {
        return $user->can('view', $competitor->project);
    }

    public function update(User $user, Competitor $competitor): bool
    {
        return $user->can('update', $competitor->project);
    }

    public function delete(User $user, Competitor $competitor): bool
    {
        return $user->can('update', $competitor->project);
    }
}
