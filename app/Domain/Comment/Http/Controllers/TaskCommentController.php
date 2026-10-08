<?php

namespace App\Domain\Comment\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Comment\Actions\PostComment;
use App\Domain\Comment\Http\Requests\StoreCommentRequest;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    public function store(StoreCommentRequest $request, Workspace $workspace, Project $project, Task $task, PostComment $post): RedirectResponse
    {
        $task->setRelation('project', $project->setRelation('workspace', $workspace));

        $post->handle($task, $request->string('body_md')->trim()->value(), Actor::user($request->user()));

        return back();
    }
}
