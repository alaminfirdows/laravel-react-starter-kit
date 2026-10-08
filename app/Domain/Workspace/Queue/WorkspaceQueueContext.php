<?php

namespace App\Domain\Workspace\Queue;

use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;

/**
 * Carries the workspace context from dispatch time into the queue worker.
 *
 * - On dispatch: the current workspace id is written into the job payload.
 * - On processing: the context is restored from the payload.
 * - After the job: the previous context is restored (needed for the sync
 *   driver, where the job runs inside the dispatching request).
 */
class WorkspaceQueueContext
{
    public const string PAYLOAD_KEY = 'workspace_id';

    /**
     * Previous contexts, one entry per running job (sync jobs can nest).
     *
     * @var list<Workspace|null|false>
     */
    protected array $stack = [];

    public function register(Dispatcher $events): void
    {
        Queue::createPayloadUsing(function (): array {
            $workspaceId = workspaceId();

            return $workspaceId === null ? [] : [self::PAYLOAD_KEY => $workspaceId];
        });

        $events->listen(JobProcessing::class, $this->restore(...));
        $events->listen(JobProcessed::class, $this->release(...));
        $events->listen(JobExceptionOccurred::class, $this->release(...));
    }

    public function restore(JobProcessing $event): void
    {
        $workspaceId = $event->job->payload()[self::PAYLOAD_KEY] ?? null;

        if ($workspaceId === null) {
            // false marks "context untouched" for this job.
            $this->stack[] = false;

            return;
        }

        $this->stack[] = currentWorkspace();

        $this->context()->setCurrentWorkspace(
            Workspace::query()->withTrashed()->findOrFail($workspaceId),
        );
    }

    public function release(): void
    {
        $previous = array_pop($this->stack);

        if ($previous === false) {
            return;
        }

        $previous === null
            ? $this->context()->forgetCurrentWorkspace()
            : $this->context()->setCurrentWorkspace($previous);
    }

    protected function context(): WorkspaceDiscoveryService
    {
        return app(WorkspaceDiscoveryService::class);
    }
}
