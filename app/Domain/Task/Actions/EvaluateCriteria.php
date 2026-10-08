<?php

namespace App\Domain\Task\Actions;

use App\Domain\Task\Data\CriterionData;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\CriterionKind;
use App\Domain\Task\Enums\EvidenceKind;
use App\Domain\Task\Models\Evidence;
use App\Domain\Task\Models\TaskAction;

/**
 * DATA_MODEL §F.2: criteria linked to an action must hold before it is done.
 * Criteria without an `action` gate the last open required action (the one that closes the task).
 */
class EvaluateCriteria
{
    /**
     * @return list<CriterionData> unmet criteria
     */
    public function handle(TaskAction $action): array
    {
        $task = $action->task;
        $criteria = array_map(CriterionData::fromArray(...), $task->completion_criteria ?? []);

        if ($criteria === []) {
            return [];
        }

        $actionKey = $action->catalogAction?->key;
        $closesTask = $action->is_required && $task->actions()
            ->whereKeyNot($action->id)
            ->where('is_required', true)
            ->whereNotIn('status', [ActionStatus::Done, ActionStatus::Skipped])
            ->doesntExist();

        $applicable = array_filter(
            $criteria,
            fn (CriterionData $c): bool => $c->action !== null ? $c->action === $actionKey : $closesTask,
        );

        $evidence = Evidence::query()
            ->where('task_id', $task->id)
            ->whereIn('criterion_key', array_map(fn (CriterionData $c): string => $c->key, $applicable))
            ->get()
            ->groupBy('criterion_key');

        return array_values(array_filter($applicable, fn (CriterionData $c): bool => ! match ($c->kind) {
            CriterionKind::Manual => true,
            CriterionKind::Evidence => $evidence->get($c->key, collect())->contains(fn (Evidence $e): bool => $e->satisfies()),
            CriterionKind::Check => $evidence->get($c->key, collect())->contains(fn (Evidence $e): bool => $e->kind === EvidenceKind::CheckResult && $e->passed === true),
        }));
    }
}
