<?php

namespace App\Domain\Task\Events;

use App\Domain\Project\Broadcasting\ProjectChannel;
use App\Domain\Task\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells open project pages to reload. Carries ids only, no task content.
 */
class TaskStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Task $task) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(ProjectChannel::name($this->task->project_id));
    }

    public function broadcastAs(): string
    {
        return 'task.status-changed';
    }

    /**
     * @return array{taskId: string, status: string}
     */
    public function broadcastWith(): array
    {
        return ['taskId' => $this->task->id, 'status' => $this->task->status->value];
    }
}
