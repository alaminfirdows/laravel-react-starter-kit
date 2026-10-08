<?php

namespace App\Domain\Workspace\Http\Controllers;

use App\Domain\Workspace\Actions\DeleteWorkspace;
use App\Domain\Workspace\Actions\UpdateWorkspace;
use App\Domain\Workspace\Actions\UpdateWorkspaceLogo;
use App\Domain\Workspace\Http\Controllers\Concerns\RedirectsToFallbackWorkspace;
use App\Domain\Workspace\Http\Requests\DeleteWorkspaceRequest;
use App\Domain\Workspace\Http\Requests\UpdateWorkspaceLogoRequest;
use App\Domain\Workspace\Http\Requests\UpdateWorkspaceRequest;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceSettingsController extends Controller
{
    use RedirectsToFallbackWorkspace;

    public function edit(Workspace $workspace): Response
    {
        return Inertia::render('workspace/settings/general', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'logoUrl' => $workspace->logo_url,
                'isPersonal' => $workspace->isPersonal(),
                'status' => $workspace->status->value,
                'createdAt' => $workspace->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace, UpdateWorkspace $updateWorkspace): RedirectResponse
    {
        $updateWorkspace->handle($workspace, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace updated.')]);

        // Slug may have changed: build the URL from the fresh slug.
        return to_route('workspace.settings.edit', ['workspace' => $workspace->slug]);
    }

    public function updateLogo(UpdateWorkspaceLogoRequest $request, Workspace $workspace, UpdateWorkspaceLogo $updateLogo): RedirectResponse
    {
        $updateLogo->handle($workspace, $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo updated.')]);

        return to_route('workspace.settings.edit');
    }

    public function destroyLogo(Workspace $workspace, UpdateWorkspaceLogo $updateLogo): RedirectResponse
    {
        Gate::authorize('update', $workspace);

        $updateLogo->remove($workspace);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return to_route('workspace.settings.edit');
    }

    public function destroy(DeleteWorkspaceRequest $request, Workspace $workspace, DeleteWorkspace $deleteWorkspace): RedirectResponse
    {
        $deleteWorkspace->handle($workspace);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace deleted.')]);

        return $this->redirectToFallbackWorkspace($request);
    }
}
