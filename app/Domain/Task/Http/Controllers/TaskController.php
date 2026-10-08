<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Project\Http\ProjectPageProps;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Http\Resources\TaskResource;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function show(Workspace $workspace, Project $project, Task $task): Response
    {
        Gate::authorize('view', $task);

        $task->load([
            'parent.parent',
            'children' => fn (HasMany $query) => $query->withCount('children')->orderBy('sort_order'),
            'actions' => fn (HasMany $query) => $query->with('promptTemplate')->orderBy('sort_order'),
        ])->setRelation('project', $project);

        return Inertia::render('projects/tasks/show', [
            new ProjectPageProps($project),
            'task' => TaskResource::make($task),
        ]);
    }
}
