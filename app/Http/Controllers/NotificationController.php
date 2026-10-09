<?php

namespace App\Http\Controllers;

use App\Domain\Task\Actions\MarkAllNotificationsRead;
use App\Domain\Task\Actions\MarkNotificationRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mark one notification read, then open what it is about.
     */
    public function read(Request $request, string $notification, MarkNotificationRead $markRead): RedirectResponse
    {
        $notification = $markRead->handle($request->user(), $notification);

        $url = $notification->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, (string) config('app.url'))
            ? redirect()->to($url)
            : back();
    }

    public function readAll(Request $request, MarkAllNotificationsRead $markAllRead): RedirectResponse
    {
        $markAllRead->handle($request->user());

        return back();
    }
}
