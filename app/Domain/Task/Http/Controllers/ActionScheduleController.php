<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\StopRecurringSchedule;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ActionScheduleController extends Controller
{
    /**
     * Stop a recurring action ("Stop schedule"); it is skipped and never runs again.
     */
    public function destroy(Request $request, Workspace $workspace, Project $project, Task $task, TaskAction $action, StopRecurringSchedule $stop): RedirectResponse
    {
        Gate::authorize('update', $task);

        try {
            $stop->handle($action, Actor::user($request->user()));
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule stopped.')]);
        } catch (InvalidTaskTransition $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
