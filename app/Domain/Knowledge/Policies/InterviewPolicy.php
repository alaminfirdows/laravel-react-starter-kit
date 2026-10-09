<?php

namespace App\Domain\Knowledge\Policies;

use App\Domain\Knowledge\Models\Interview;
use App\Models\User;

/**
 * Viewers read, editors write (same as knowledge documents).
 */
class InterviewPolicy
{
    public function view(User $user, Interview $interview): bool
    {
        return $user->can('view', $interview->project);
    }

    public function update(User $user, Interview $interview): bool
    {
        return $user->can('update', $interview->project);
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $user->can('update', $interview->project);
    }
}
