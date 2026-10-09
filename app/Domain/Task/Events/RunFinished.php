<?php

namespace App\Domain\Task\Events;

use App\Domain\Project\Broadcasting\ProjectChannel;
use App\Domain\Task\Models\ActionRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An action run reached a final status. Carries ids only, no output.
 */
class RunFinished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public ActionRun $run) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(ProjectChannel::name($this->run->project_id));
    }

    public function broadcastAs(): string
    {
        return 'run.finished';
    }

    /**
     * @return array{runId: string, taskId: string, status: string}
     */
    public function broadcastWith(): array
    {
        return ['runId' => $this->run->id, 'taskId' => $this->run->task_id, 'status' => $this->run->status->value];
    }
}
