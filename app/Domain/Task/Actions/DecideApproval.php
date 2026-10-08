<?php

namespace App\Domain\Task\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Exceptions\InvalidActionTransition;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Support\Facades\DB;

/**
 * Only people decide (§F.5). Either way the action goes back to `ready`; an approved one can then complete.
 */
class DecideApproval
{
    public function __construct(
        protected SyncTaskFromActions $sync,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(Approval $approval, Actor $actor, bool $approve, ?string $note = null): Approval
    {
        if ($actor->type !== ActorType::User) {
            throw InvalidActionTransition::agentCannotApprove();
        }

        if ($approval->status !== ApprovalStatus::Pending) {
            throw InvalidActionTransition::approvalDecided($approval);
        }

        return DB::transaction(function () use ($approval, $actor, $approve, $note): Approval {
            $approval->update([
                'status' => $approve ? ApprovalStatus::Approved : ApprovalStatus::Rejected,
                'decided_by_type' => $actor->type,
                'decided_by_id' => $actor->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $subject = $approval->subject;

            if ($subject instanceof TaskAction && $subject->status === ActionStatus::AwaitingApproval) {
                $subject->forceFill(['status' => ActionStatus::Ready])->save();
                $this->sync->handle($subject->task, $actor);
            }

            $this->activity->record('approval.decided', $approval, ['status' => $approval->status->value], $actor);

            return $approval;
        });
    }
}
