<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Actions\MarkTaskDone;
use App\Domain\Task\Actions\ReopenTask;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TaskCompletionController extends Controller
{
    public function store(Request $request, Workspace $workspace, Project $project, Task $task, MarkTaskDone $markDone): RedirectResponse
    {
        Gate::authorize('update', $task);

        return $this->attempt(fn () => $markDone->handle($task, Actor::user($request->user())), __('Marked as done.'));
    }

    public function destroy(Request $request, Workspace $workspace, Project $project, Task $task, ReopenTask $reopen): RedirectResponse
    {
        Gate::authorize('update', $task);

        return $this->attempt(fn () => $reopen->handle($task, Actor::user($request->user())), __('Reopened.'));
    }

    private function attempt(Closure $callback, string $success): RedirectResponse
    {
        try {
            $callback();
            Inertia::flash('toast', ['type' => 'success', 'message' => $success]);
        } catch (InvalidTaskTransition $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
