<?php

namespace App\Domain\Task\Actions;

use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use App\Domain\Task\Models\ActionRun;
use App\Domain\Task\Notifications\ActionFailed;
use App\Domain\Task\Notifications\RunFinished;
use App\Models\User;

/**
 * Tells the user who started an in-app run how it ended.
 * A run that asked for approval is covered by the approval notification.
 */
class NotifyRunOutcome
{
    public function handle(ActionRun $run, User $user): void
    {
        if (! in_array($run->channel, [RunChannel::AppAi, RunChannel::System], true)) {
            return;
        }

        match (true) {
            $run->status === RunStatus::Failed => $user->notify(new ActionFailed($run)),
            $run->status === RunStatus::Succeeded && $run->action->status !== ActionStatus::AwaitingApproval => $user->notify(new RunFinished($run)),
            default => null,
        };
    }
}
