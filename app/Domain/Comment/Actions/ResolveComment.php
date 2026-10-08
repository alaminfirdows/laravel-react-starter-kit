<?php

namespace App\Domain\Comment\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Comment\Models\Comment;

class ResolveComment
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * Resolve, or reopen with `$resolved = false`. No change → no activity.
     */
    public function handle(Comment $comment, Actor $actor, bool $resolved = true): Comment
    {
        if ($comment->isResolved() === $resolved) {
            return $comment;
        }

        $comment->forceFill([
            'resolved_at' => $resolved ? now() : null,
            'resolved_by_id' => $resolved ? $actor->id : null,
        ])->save();

        $this->activity->record($resolved ? 'comment.resolved' : 'comment.reopened', $comment->commentable, [
            'comment_id' => $comment->id,
        ], $actor);

        return $comment;
    }
}
