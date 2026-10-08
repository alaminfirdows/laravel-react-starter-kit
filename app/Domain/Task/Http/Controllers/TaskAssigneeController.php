<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\AssignTask;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Http\Requests\AssignTaskRequest;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TaskAssigneeController extends Controller
{
    public function update(AssignTaskRequest $request, Workspace $workspace, Project $project, Task $task, AssignTask $assign): RedirectResponse
    {
        $assignee = $request->assignee();

        try {
            $assign->handle($task, $assignee, Actor::user($request->user()));
        } catch (InvalidTaskTransition $e) {
            throw ValidationException::withMessages(['assignee_id' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $assignee ? __('Assigned to :name.', ['name' => $assignee->name]) : __('Unassigned.')]);

        return back();
    }
}
