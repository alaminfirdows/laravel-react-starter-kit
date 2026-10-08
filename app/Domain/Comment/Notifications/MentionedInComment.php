<?php

namespace App\Domain\Comment\Notifications;

use App\Domain\Comment\Models\Comment;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Notifications\TaskNotification;
use Illuminate\Support\Str;
use LogicException;

class MentionedInComment extends TaskNotification
{
    public function __construct(public Comment $comment)
    {
        parent::__construct();
    }

    protected function task(): Task
    {
        $task = $this->comment->commentable;

        if (! $task instanceof Task) {
            throw new LogicException('Mentions are only sent for task comments.');
        }

        return $task;
    }

    protected function title(): string
    {
        return __(':author mentioned you on :task', [
            'author' => $this->comment->author->name ?? __('Someone'),
            'task' => $this->task()->title,
        ]);
    }

    protected function body(): string
    {
        return Str::limit(strip_tags($this->comment->body_md), 300);
    }
}
