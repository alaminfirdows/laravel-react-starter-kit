<?php

namespace App\Domain\Comment\Events;

use App\Domain\Comment\Models\Comment;
use App\Domain\Project\Broadcasting\ProjectChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A comment was posted on a project task. Carries ids only, no body.
 */
class CommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Comment $comment) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(ProjectChannel::name($this->comment->project_id));
    }

    /**
     * @return array{commentId: string, taskId: string}
     */
    public function broadcastWith(): array
    {
        return ['commentId' => $this->comment->id, 'taskId' => $this->comment->commentable_id];
    }
}
