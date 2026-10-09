<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\UpgradeTaskFromCatalog;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Http\Requests\UpgradeTaskRequest;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TaskCatalogUpgradeController extends Controller
{
    public function store(UpgradeTaskRequest $request, Workspace $workspace, Project $project, Task $task, UpgradeTaskFromCatalog $upgrade): RedirectResponse
    {
        try {
            $upgrade->handle($task, $request->fields(), Actor::user($request->user()));
        } catch (InvalidTaskTransition $e) {
            throw ValidationException::withMessages(['fields' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task updated to the latest catalog version.')]);

        return back();
    }
}
