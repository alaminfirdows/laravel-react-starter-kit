<?php

namespace App\Domain\Workspace\Http\Controllers;

use App\Domain\Workspace\Actions\RemoveMember;
use App\Domain\Workspace\Actions\TransferOwnership;
use App\Domain\Workspace\Actions\UpdateMemberRole;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Http\Controllers\Concerns\RedirectsToFallbackWorkspace;
use App\Domain\Workspace\Http\Requests\TransferOwnershipRequest;
use App\Domain\Workspace\Http\Requests\UpdateMemberRoleRequest;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceMemberController extends Controller
{
    use RedirectsToFallbackWorkspace;

    public function index(Request $request, Workspace $workspace): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $actorRole = $actor->workspaceRole($workspace);
        $canUpdateAny = $actor->can('updateAnyMember', $workspace);
        $canRemoveAny = $actor->can('removeAnyMember', $workspace);

        $members = $workspace->members()
            ->orderBy('workspace_members.created_at')
            ->get()
            ->map(function (User $member) use ($actor, $actorRole, $canUpdateAny, $canRemoveAny): array {
                $role = $member->membership->role;
                $outranked = ! $actor->is($member) && $actorRole?->outranks($role) === true;

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $role->value,
                    'roleLabel' => $role->label(),
                    'joinedAt' => $member->membership->joined_at?->toIso8601String(),
                    'isCurrentUser' => $actor->is($member),
                    'canUpdate' => $canUpdateAny && $outranked,
                    'canRemove' => $canRemoveAny && $outranked,
                ];
            });

        return Inertia::render('workspace/settings/members', [
            'members' => $members,
            'assignableRoles' => WorkspaceRole::options($actorRole?->assignableRoles() ?? []),
        ]);
    }

    public function update(UpdateMemberRoleRequest $request, Workspace $workspace, User $member, UpdateMemberRole $updateMemberRole): RedirectResponse
    {
        $updateMemberRole->handle($workspace, $member, $request->role());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('workspace.members.index');
    }

    public function destroy(Workspace $workspace, User $member, RemoveMember $removeMember): RedirectResponse
    {
        Gate::authorize('removeMember', [$workspace, $member]);

        $removeMember->handle($workspace, $member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('workspace.members.index');
    }

    public function leave(Request $request, Workspace $workspace, RemoveMember $removeMember): RedirectResponse
    {
        Gate::authorize('leave', $workspace);

        $removeMember->handle($workspace, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left :workspace.', ['workspace' => $workspace->name])]);

        return $this->redirectToFallbackWorkspace($request);
    }

    public function transferOwnership(TransferOwnershipRequest $request, Workspace $workspace, User $member, TransferOwnership $transferOwnership): RedirectResponse
    {
        $transferOwnership->handle($workspace, $member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ownership transferred to :name.', ['name' => $member->name])]);

        return to_route('workspace.members.index');
    }
}
