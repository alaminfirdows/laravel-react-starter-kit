<?php

namespace App\Domain\Comment\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Comment\Events\CommentPosted;
use App\Domain\Comment\Models\Comment;
use App\Domain\Comment\Notifications\MentionedInComment;
use App\Domain\Comment\Support\MentionParser;
use App\Domain\Task\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class PostComment
{
    public function __construct(
        protected ActivityRecorder $activity,
        protected MentionParser $mentions,
    ) {}

    public function handle(Task $task, string $bodyMd, Actor $actor): Comment
    {
        $comment = $task->comments()->create([
            'workspace_id' => $task->workspace_id,
            'project_id' => $task->project_id,
            'author_type' => $actor->type,
            'author_id' => $actor->id,
            'client_name' => $actor->clientName,
            'body_md' => $bodyMd,
        ]);
        $comment->setRelation('commentable', $task)->load('author:id,name');

        $mentioned = $this->mentions->members($task->project->workspace, $bodyMd)
            ->reject(fn (User $user): bool => $user->id === $actor->id);

        $this->activity->record('comment.posted', $task, [
            'comment_id' => $comment->id,
            'mentioned' => $mentioned->pluck('id')->all(),
        ], $actor);

        Notification::send($mentioned, new MentionedInComment($comment));
        CommentPosted::dispatch($comment);

        return $comment;
    }
}
