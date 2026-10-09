<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Task\Notifications\ApprovalRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RequestApproval
{
    public function __construct(
        protected SyncTaskFromActions $sync,
        protected ActivityRecorder $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(TaskAction $action, Actor $actor, string $summaryMd, array $payload = []): Approval
    {
        if ($action->status->isClosed()) {
            throw InvalidActionTransition::closed($action);
        }

        $pending = $action->approvals()->where('status', ApprovalStatus::Pending)->first();

        if ($pending !== null) {
            return $pending;
        }

        return DB::transaction(function () use ($action, $actor, $summaryMd, $payload): Approval {
            $approval = $action->approvals()->make([
                'project_id' => $action->project_id,
                'requested_by_type' => $actor->type,
                'requested_by_id' => $actor->id,
                'requested_by_client' => $actor->clientName,
                'summary_md' => $summaryMd,
                'payload' => $payload ?: null,
            ]);
            $approval->forceFill(['status' => ApprovalStatus::Pending])->save();

            $action->forceFill(['status' => ActionStatus::AwaitingApproval])->save();

            $this->activity->record('approval.requested', $action, ['approval_id' => $approval->id], $actor);
            $this->sync->handle($action->task, $actor);
            Notification::send($action->task->project->workspace->editors()->get(), new ApprovalRequested($approval, $action));

            return $approval;
        });
    }
}
