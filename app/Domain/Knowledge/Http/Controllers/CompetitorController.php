<?php

namespace App\Domain\Knowledge\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Actions\DeleteCompetitor;
use App\Domain\Knowledge\Actions\SaveCompetitor;
use App\Domain\Knowledge\Http\Requests\SaveCompetitorRequest;
use App\Domain\Knowledge\Models\Competitor;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CompetitorController extends Controller
{
    public function store(SaveCompetitorRequest $request, Workspace $workspace, Project $project, SaveCompetitor $save): RedirectResponse
    {
        $save->handle($project, $request->toData(), Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competitor saved.')]);

        return back();
    }

    public function update(SaveCompetitorRequest $request, Workspace $workspace, Project $project, Competitor $competitor, SaveCompetitor $save): RedirectResponse
    {
        Gate::authorize('update', $competitor);

        $save->handle($project, $request->toData(), Actor::user($request->user()), $competitor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competitor saved.')]);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, Project $project, Competitor $competitor, DeleteCompetitor $delete): RedirectResponse
    {
        Gate::authorize('delete', $competitor);

        $delete->handle($competitor, Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competitor deleted.')]);

        return back();
    }
}
