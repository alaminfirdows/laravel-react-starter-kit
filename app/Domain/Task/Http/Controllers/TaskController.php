<?php

namespace App\Domain\Task\Http\Controllers;

use App\Domain\Catalog\Actions\DiffCatalogVersion;
use App\Domain\Comment\Http\Resources\CommentResource;
use App\Domain\Project\Http\ProjectPageProps;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Http\Resources\CatalogFieldDiffResource;
use App\Domain\Task\Http\Resources\TaskResource;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * Latest runs shown per action.
     */
    private const int RUN_HISTORY = 5;

    public function show(Workspace $workspace, Project $project, Task $task, DiffCatalogVersion $diff): Response
    {
        Gate::authorize('view', $task);

        $task->load([
            'catalogTask.skills',
            'assignee:id,name',
            'parent.parent',
            'children' => fn (HasMany $query) => $query->withCount('children')->orderBy('sort_order'),
            'actions' => fn (HasMany $query) => $query->with(['promptTemplate', 'evidence', 'approvals', 'runs' => fn (Relation $runs) => $runs->limit(self::RUN_HISTORY)])->orderBy('sort_order'),
        ])->setRelation('project', $project);
        $task->actions->each->setRelation('task', $task);

        return Inertia::render('projects/tasks/show', [
            new ProjectPageProps($project),
            'task' => TaskResource::make($task),
            'comments' => fn () => CommentResource::collection($task->comments()->with('author:id,name')->get()),
            'assignees' => fn (): array => $workspace->editors()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])
                ->all(),
            'catalogDiff' => Inertia::optional(fn () => CatalogFieldDiffResource::collection($diff->handle($task))),
        ]);
    }
}
