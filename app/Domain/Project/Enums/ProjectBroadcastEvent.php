<?php

namespace App\Domain\Project\Enums;

/**
 * Event names broadcast on the private project channel. The client listens with a leading dot.
 */
enum ProjectBroadcastEvent: string
{
    case TaskStatusChanged = 'task.status-changed';
    case RunFinished = 'run.finished';
    case CommentPosted = 'comment.posted';
}
