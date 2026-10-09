<?php

namespace App\Domain\Task\Actions;

use App\Models\User;

/**
 * Mark all of the user's own unread notifications read. Personal inbox state, so no activity entry.
 */
class MarkAllNotificationsRead
{
    public function handle(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
