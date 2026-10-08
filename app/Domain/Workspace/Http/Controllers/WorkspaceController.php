<?php

namespace App\Domain\Workspace\Http\Controllers;

use App\Domain\Workspace\Actions\CreateWorkspace;
use App\Domain\Workspace\Http\Requests\StoreWorkspaceRequest;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages outside a workspace URL: list, create, and the /dashboard redirect.
 */
class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('workspaces/index', [
            'workspaces' => $user->toUserWorkspaces(),
            'pendingInvitations' => WorkspaceInvitation::query()
                ->forEmail($user->email)
                ->pending()
                ->whereHas('workspace')
                ->with(['workspace:id,name,slug,logo_path', 'inviter:id,name'])
                ->latest()
                ->get()
                ->map(fn (WorkspaceInvitation $invitation): array => [
                    'code' => $invitation->code,
                    'role' => $invitation->role->value,
                    'roleLabel' => $invitation->role->label(),
                    'workspaceName' => $invitation->workspace->name,
                    'workspaceLogoUrl' => $invitation->workspace->logo_url,
                    'inviterName' => $invitation->inviter?->name,
                    'expiresAt' => $invitation->expires_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $workspace = $createWorkspace->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace created.')]);

        return to_route('dashboard', ['workspace' => $workspace->slug]);
    }

    /**
     * /dashboard (Fortify home) → /{current-workspace}/dashboard.
     */
    public function redirectToCurrent(Request $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $workspace = $user->resolveCurrentWorkspace() ?? $createWorkspace->personal($user);

        return to_route('dashboard', ['workspace' => $workspace->slug]);
    }
}
