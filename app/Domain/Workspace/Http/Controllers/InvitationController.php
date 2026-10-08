<?php

namespace App\Domain\Workspace\Http\Controllers;

use App\Domain\Workspace\Actions\AcceptInvitation;
use App\Domain\Workspace\Actions\DeclineInvitation;
use App\Domain\Workspace\Exceptions\InvalidInvitationException;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invitation link from the email (/invitations/{code}). Open to guests:
 * a guest is sent to log in or register, then back here.
 */
class InvitationController extends Controller
{
    public function show(Request $request, WorkspaceInvitation $invitation): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            $request->session()->put('url.intended', route('invitations.show', $invitation->code));
        }

        $invitation->loadMissing(['workspace' => fn ($query) => $query->withTrashed(), 'inviter:id,name']);
        $workspace = $invitation->workspace;

        $status = match (true) {
            $workspace->trashed() => 'unavailable',
            $invitation->isAccepted() => 'accepted',
            $invitation->isExpired() => 'expired',
            default => 'pending',
        };

        return Inertia::render('invitations/show', [
            'invitation' => [
                'code' => $invitation->code,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'roleLabel' => $invitation->role->label(),
                'workspaceName' => $workspace->name,
                'workspaceLogoUrl' => $workspace->logo_url,
                'inviterName' => $invitation->inviter?->name,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
                'status' => $status,
            ],
            'isGuest' => $user === null,
            'emailMatches' => $user !== null && $invitation->isFor($user),
            'workspaceSlug' => $user?->belongsToWorkspace($workspace) ? $workspace->slug : null,
        ]);
    }

    public function accept(Request $request, WorkspaceInvitation $invitation, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        try {
            $workspace = $acceptInvitation->handle($invitation, $request->user());
        } catch (InvalidInvitationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('invitations.show', $invitation->code);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :workspace.', ['workspace' => $workspace->name])]);

        return to_route('dashboard', ['workspace' => $workspace->slug]);
    }

    public function decline(Request $request, WorkspaceInvitation $invitation, DeclineInvitation $declineInvitation): RedirectResponse
    {
        try {
            $declineInvitation->handle($invitation, $request->user());
        } catch (InvalidInvitationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('invitations.show', $invitation->code);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('workspaces.index');
    }
}
