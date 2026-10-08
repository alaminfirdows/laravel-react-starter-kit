<?php

namespace App\Domain\Project\Http\Controllers;

use App\Domain\Activity\Http\Resources\ActivityResource;
use App\Domain\Activity\Models\Activity;
use App\Domain\Project\Actions\CreateProject;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Http\ProjectPageProps;
use App\Domain\Project\Http\Requests\StoreProjectRequest;
use App\Domain\Project\Http\Resources\ProjectResource;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Http\Resources\TaskSummaryResource;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('viewAny', Project::class);

        $projects = $workspace->projects()
            ->with('brand.logo')
            ->withAvg(['tasks as leaf_progress' => fn (Builder $query) => $query->whereDoesntHave('children')], 'progress_pct')
            ->latest()
            ->get();

        return Inertia::render('projects/index', [
            'projects' => ProjectResource::collection($projects),
            'can' => ['create' => $request->user()->can('create', Project::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/create', ['phases' => ProjectPhase::options()]);
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        $project = $createProject->handle(
            $request->user(),
            $request->enum('phase', ProjectPhase::class),
            $request->string('name')->trim()->toString(),
        );

        return to_route('projects.setup.edit', ['project' => $project->slug, 'step' => ProjectSetupStep::Identity]);
    }

    public function show(Workspace $workspace, Project $project): Response
    {
        Gate::authorize('view', $project);

        $nextTask = $project->tasks()
            ->whereNotIn('status', [TaskStatus::Done, TaskStatus::Skipped, TaskStatus::Locked])
            ->whereDoesntHave('children')
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->first();

        return Inertia::render('projects/overview', [
            new ProjectPageProps($project),
            'nextTask' => $nextTask ? TaskSummaryResource::make($nextTask) : null,
            'activity' => Inertia::optional(fn () => ActivityResource::collection(
                Activity::query()->where('project_id', $project->id)->latest('id')->limit(20)->get(),
            )),
        ]);
    }
}
