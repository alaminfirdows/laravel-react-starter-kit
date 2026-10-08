<?php

namespace App\Domain\Activity\Http\Controllers;

use App\Domain\Activity\Http\Requests\ActivityFilterRequest;
use App\Domain\Activity\Http\Resources\ActivityResource;
use App\Domain\Activity\Queries\ActivityFeed;
use App\Domain\Project\Http\ProjectPageProps;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectActivityController extends Controller
{
    public function index(ActivityFilterRequest $request, Workspace $workspace, Project $project, ActivityFeed $feed): Response
    {
        Gate::authorize('view', $project);

        $filters = $request->filters();

        return Inertia::render('projects/activity', [
            new ProjectPageProps($project),
            'activity' => ActivityResource::collection($feed->handle($workspace, $filters, $project->id)),
            'filters' => $filters->toArray(),
            'options' => fn (): array => $feed->options($workspace, $project->id),
        ]);
    }
}
