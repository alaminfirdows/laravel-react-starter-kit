<?php

namespace App\Domain\Project\Http\Controllers;

use App\Domain\Project\Actions\UpdateProjectLogo;
use App\Domain\Project\Http\Requests\UpdateProjectLogoRequest;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProjectLogoController extends Controller
{
    public function store(UpdateProjectLogoRequest $request, Workspace $workspace, Project $project, UpdateProjectLogo $updateLogo): RedirectResponse
    {
        $updateLogo->handle($project, $request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo updated.')]);

        return back();
    }
}
