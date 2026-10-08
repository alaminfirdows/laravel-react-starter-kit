<?php

namespace App\Domain\Comment\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Comment\Actions\ResolveComment;
use App\Domain\Comment\Models\Comment;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentResolutionController extends Controller
{
    public function store(Request $request, Workspace $workspace, Project $project, Task $task, Comment $comment, ResolveComment $resolve): RedirectResponse
    {
        return $this->apply($request, $task, $comment, $resolve, true);
    }

    public function destroy(Request $request, Workspace $workspace, Project $project, Task $task, Comment $comment, ResolveComment $resolve): RedirectResponse
    {
        return $this->apply($request, $task, $comment, $resolve, false);
    }

    private function apply(Request $request, Task $task, Comment $comment, ResolveComment $resolve, bool $resolved): RedirectResponse
    {
        Gate::authorize('update', $task);

        $resolve->handle($comment->setRelation('commentable', $task), Actor::user($request->user()), $resolved);

        return back();
    }
}
