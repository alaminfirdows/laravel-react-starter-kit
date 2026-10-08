<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\RunActionInApp;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ActionRunController extends Controller
{
    /**
     * Start an in-app run ("Run with AI" / "Run check"); the task page polls until it ends.
     */
    public function store(Request $request, Workspace $workspace, Project $project, Task $task, TaskAction $action, RunActionInApp $run): RedirectResponse
    {
        Gate::authorize('update', $task);

        try {
            $run->handle($action, $request->user());
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Run started.')]);
        } catch (InvalidTaskTransition $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
