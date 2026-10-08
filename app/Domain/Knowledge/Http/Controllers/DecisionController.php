<?php

namespace App\Domain\Knowledge\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Actions\RecordDecision;
use App\Domain\Knowledge\Http\Requests\StoreDecisionRequest;
use App\Domain\Knowledge\Http\Resources\DecisionResource;
use App\Domain\Project\Http\ProjectPageProps;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DecisionController extends Controller
{
    public function index(Workspace $workspace, Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('projects/decisions/index', [
            new ProjectPageProps($project),
            'decisions' => DecisionResource::collection(
                $project->decisions()->with('owner:id,name')->latest('decided_on')->latest()->get(),
            ),
        ]);
    }

    public function store(StoreDecisionRequest $request, Workspace $workspace, Project $project, RecordDecision $record): RedirectResponse
    {
        $record->handle($project, $request->toData(), Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Decision recorded.')]);

        return back();
    }
}
