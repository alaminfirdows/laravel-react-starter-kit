<?php

namespace App\Domain\Workspace\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * On pages outside a workspace URL (profile settings, /workspaces), make
 * route('dashboard') etc. point at the user's current workspace.
 * DiscoverWorkspace overrides this on tenant routes.
 */
class SetWorkspaceUrlDefaults
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->current_workspace_id !== null) {
            $slug = $user->currentWorkspace?->slug;

            if ($slug !== null) {
                URL::defaults(['workspace' => $slug]);
            }
        }

        return $next($request);
    }
}
