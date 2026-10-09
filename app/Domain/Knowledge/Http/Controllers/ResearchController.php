<?php

namespace App\Domain\Knowledge\Http\Controllers;

use App\Domain\Knowledge\Http\Resources\CompetitorResource;
use App\Domain\Knowledge\Http\Resources\InterviewResource;
use App\Domain\Project\Http\ProjectPageProps;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResearchController extends Controller
{
    public function index(Workspace $workspace, Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('projects/research/index', [
            new ProjectPageProps($project),
            'interviews' => InterviewResource::collection(
                $project->interviews()->latest('interviewed_on')->latest()->get(),
            ),
            'competitors' => CompetitorResource::collection(
                $project->competitors()->orderBy('name')->get(),
            ),
        ]);
    }
}
