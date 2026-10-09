<?php

namespace App\Domain\Workspace\Http\Controllers;

use App\Domain\Workspace\Actions\InviteMember;
use App\Domain\Workspace\Actions\ResendInvitation;
use App\Domain\Workspace\Actions\RevokeInvitation;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Exceptions\InvalidInvitationException;
use App\Domain\Workspace\Http\Requests\InviteMemberRequest;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invitations managed by workspace admins (tenant URL).
 */
class WorkspaceInvitationController extends Controller
{
    /**
     * Pending invitee emails are for admins only; other members get an empty list.
     */
    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('view', $workspace);

        /** @var User $actor */
        $actor = $request->user();
        $canManage = $actor->can('inviteMember', $workspace) || $actor->can('cancelInvitation', $workspace);

        return Inertia::render('workspace/settings/invitations', [
            'invitations' => ! $canManage ? [] : $workspace->invitations()
                ->whereNull('accepted_at')
                ->with('inviter:id,name')
                ->latest()
                ->get()
                ->map(fn (WorkspaceInvitation $invitation): array => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'roleLabel' => $invitation->role->label(),
                    'inviterName' => $invitation->inviter?->name,
                    'isExpired' => $invitation->isExpired(),
                    'expiresAt' => $invitation->expires_at?->toIso8601String(),
                    'createdAt' => $invitation->created_at?->toIso8601String(),
                ]),
            'assignableRoles' => WorkspaceRole::options(
                $actor->workspaceRole($workspace)?->assignableRoles() ?? [],
            ),
        ]);
    }

    public function store(InviteMemberRequest $request, Workspace $workspace, InviteMember $inviteMember): RedirectResponse
    {
        $inviteMember->handle($workspace, $request->user(), $request->validated('email'), $request->role());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('workspace.invitations.index');
    }

    public function resend(Workspace $workspace, WorkspaceInvitation $invitation, ResendInvitation $resendInvitation): RedirectResponse
    {
        Gate::authorize('inviteMember', $workspace);

        try {
            $resendInvitation->handle($invitation);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent again.')]);
        } catch (InvalidInvitationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return to_route('workspace.invitations.index');
    }

    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation, Request $request, RevokeInvitation $revokeInvitation): RedirectResponse
    {
        Gate::authorize('cancelInvitation', $workspace);

        $revokeInvitation->handle($invitation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('workspace.invitations.index');
    }
}
