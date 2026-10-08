<?php

namespace App\Domain\Project\Http\Controllers;

use App\Domain\Project\Actions\ActivateProject;
use App\Domain\Project\Actions\UpdateProjectSetup;
use App\Domain\Project\Enums\BusinessModel;
use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Enums\Stage;
use App\Domain\Project\Http\Requests\UpdateProjectSetupRequest;
use App\Domain\Project\Http\Resources\ProjectSetupResource;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectSetupController extends Controller
{
    public function edit(Workspace $workspace, Project $project, ProjectSetupStep $step): Response
    {
        Gate::authorize('update', $project);

        return Inertia::render('projects/setup', [
            'project' => ProjectSetupResource::make($project->loadMissing('brand.logo')),
            'step' => $step,
            'steps' => ProjectSetupStep::options(),
            'options' => [
                'businessModels' => BusinessModel::options(),
                'stages' => Stage::options(),
            ],
        ]);
    }

    public function update(
        UpdateProjectSetupRequest $request,
        Workspace $workspace,
        Project $project,
        ProjectSetupStep $step,
        UpdateProjectSetup $updateSetup,
        ActivateProject $activate,
    ): RedirectResponse {
        $updateSetup->handle($project, $step, $request->validated());

        if ($next = $step->next()) {
            return to_route('projects.setup.edit', ['project' => $project->slug, 'step' => $next]);
        }

        try {
            $activate->handle($project);
        } catch (ValidationException $e) {
            return to_route('projects.setup.edit', [
                'project' => $project->slug,
                'step' => ProjectSetupStep::firstIncomplete($project) ?? ProjectSetupStep::Identity,
            ])->withErrors($e->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project ready. Let\'s start.')]);

        return to_route('projects.show', ['project' => $project->slug]);
    }
}
