<?php

namespace App\Domain\Knowledge\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Actions\DeleteInterview;
use App\Domain\Knowledge\Actions\SaveInterview;
use App\Domain\Knowledge\Http\Requests\SaveInterviewRequest;
use App\Domain\Knowledge\Models\Interview;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InterviewController extends Controller
{
    public function store(SaveInterviewRequest $request, Workspace $workspace, Project $project, SaveInterview $save): RedirectResponse
    {
        $save->handle($project, $request->toData(), Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interview saved.')]);

        return back();
    }

    public function update(SaveInterviewRequest $request, Workspace $workspace, Project $project, Interview $interview, SaveInterview $save): RedirectResponse
    {
        Gate::authorize('update', $interview);

        $save->handle($project, $request->toData(), Actor::user($request->user()), $interview);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interview saved.')]);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, Project $project, Interview $interview, DeleteInterview $delete): RedirectResponse
    {
        Gate::authorize('delete', $interview);

        $delete->handle($interview, Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Interview deleted.')]);

        return back();
    }
}
