<?php

namespace App\Domain\Task\Actions;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Mark one of the user's own notifications read. Personal inbox state, so no activity entry.
 */
class MarkNotificationRead
{
    public function handle(User $user, string $notificationId): DatabaseNotification
    {
        $notification = $user->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return $notification;
    }
}
