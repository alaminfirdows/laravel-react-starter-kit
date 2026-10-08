<?php

namespace App\Domain\Task\Exceptions;

use App\Domain\Task\Data\CriterionData;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\TaskAction;

class InvalidActionTransition extends InvalidTaskTransition
{
    /**
     * @param  list<CriterionData>  $unmet
     */
    final public function __construct(string $message, public readonly array $unmet = [])
    {
        parent::__construct($message);
    }

    public static function closed(TaskAction $action): static
    {
        return new static(__('":title" is already closed.', ['title' => $action->title]));
    }

    public static function taskLocked(TaskAction $action): static
    {
        return new static(__('":title" is locked until its dependencies are done.', ['title' => $action->task->title]));
    }

    public static function noActiveRun(TaskAction $action): static
    {
        return new static(__('":title" has no started run. Call start_action first.', ['title' => $action->title]));
    }

    public static function notAppAi(TaskAction $action): static
    {
        return new static(__('":title" does not run with in-app AI.', ['title' => $action->title]));
    }

    public static function alreadyRunning(TaskAction $action): static
    {
        return new static(__('":title" is already running.', ['title' => $action->title]));
    }

    public static function approvalRequired(TaskAction $action): static
    {
        return new static(__('":title" needs an approved approval first.', ['title' => $action->title]));
    }

    /**
     * @param  list<CriterionData>  $unmet
     */
    public static function unmetCriteria(TaskAction $action, array $unmet): static
    {
        $labels = implode(', ', array_map(fn (CriterionData $c): string => $c->label, $unmet));

        return new static(__('":title" is missing: :criteria.', ['title' => $action->title, 'criteria' => $labels]), $unmet);
    }

    public static function unknownCriterion(TaskAction $action, string $key): static
    {
        return new static(__('":title" has no criterion [:key].', ['title' => $action->task->title, 'key' => $key]));
    }

    public static function approvalDecided(Approval $approval): static
    {
        return new static(__('This approval is already :status.', ['status' => $approval->status->value]));
    }

    public static function agentCannotApprove(): static
    {
        return new static(__('Agents cannot decide approvals.'));
    }
}
