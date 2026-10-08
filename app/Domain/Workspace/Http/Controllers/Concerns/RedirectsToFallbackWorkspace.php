<?php

namespace App\Domain\Workspace\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsToFallbackWorkspace
{
    /**
     * After the user lost access to a workspace (left, deleted).
     */
    protected function redirectToFallbackWorkspace(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $fallback = $user->refresh()->resolveCurrentWorkspace();

        return $fallback
            ? to_route('dashboard', ['workspace' => $fallback->slug])
            : to_route('workspaces.index');
    }
}
