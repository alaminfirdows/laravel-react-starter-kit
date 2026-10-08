<?php

namespace App\Mcp\Support;

use App\Domain\Catalog\Models\CatalogResource;
use App\Domain\Catalog\Models\Skill;
use App\Domain\Prompt\Actions\RenderFullPrompt;
use App\Domain\Task\Actions\EvaluateCriteria;
use App\Domain\Task\Data\CriterionData;
use App\Domain\Task\Models\Evidence;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;

/**
 * Compact Markdown views of tasks and actions for MCP tools, prompts and resources.
 */
class TaskMarkdown
{
    public function __construct(
        private EvaluateCriteria $evaluateCriteria,
        private RenderFullPrompt $renderFullPrompt,
    ) {}

    public function line(Task $task): string
    {
        return "- [{$task->status->value}] {$task->title} (`{$task->id}`, {$task->progress_pct}%)";
    }

    public function task(Task $task): string
    {
        $task->loadMissing(['children', 'actions', 'evidence', 'catalogTask.skills', 'catalogTask.resources']);

        $sections = [
            "# {$task->title}",
            "Task `{$task->id}` · project `{$task->project_id}` · status {$task->status->value} · {$task->progress_pct}% · priority {$task->priority->value}",
            $task->summary,
            $task->body_md,
            $this->list('Completion criteria', array_map(
                fn (CriterionData $criterion): string => "- `{$criterion->key}` ({$criterion->kind->value}".($criterion->action ? ", action {$criterion->action}" : '')."): {$criterion->label}",
                $this->criteria($task),
            )),
            $this->list('Expected outputs', array_map(
                fn (array $output): string => '- '.implode(' — ', array_filter(array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $output))),
                $task->expected_outputs ?? [],
            )),
            $this->list('Subtasks', $task->children->map(fn (Task $child): string => $this->line($child))->all()),
            $this->list('Actions', $task->actions->map(fn (TaskAction $action): string => $this->actionLine($action))->all()),
            $this->list('Skills', $task->catalogTask?->skills->map(
                fn (Skill $skill): string => "- {$skill->key}".($skill->pivot?->getAttribute('required') ? ' (required)' : ''),
            )->all() ?? []),
            $this->list('Resources', $task->catalogTask?->resources->map(
                fn (CatalogResource $resource): string => "- [{$resource->title}](".($resource->url ?? '#').") ({$resource->type->value})".(($note = $resource->pivot?->getAttribute('note')) ? " — {$note}" : ''),
            )->all() ?? []),
            $this->list('Evidence', $task->evidence->map(fn (Evidence $evidence): string => $this->evidenceLine($evidence))->all()),
            'Call get_action for the full instructions of a step, then start_action before you work on it.',
        ];

        return $this->join($sections);
    }

    public function action(TaskAction $action): string
    {
        $action->loadMissing(['task', 'lastRun', 'evidence', 'approvals']);

        $unmet = $action->status->isClosed() ? [] : $this->evaluateCriteria->handle($action);
        $run = $action->lastRun;

        return $this->join([
            "# {$action->title}",
            "Action `{$action->id}` · task `{$action->task_id}` ({$action->task->title}) · type {$action->type->value} · executor {$action->executor->value} · status {$action->status->value}"
                .($action->is_required ? ' · required' : '')
                .($action->requires_approval ? ' · needs approval before completion' : ''),
            $run ? "Last run `{$run->id}`: {$run->status->value} via {$run->channel->value}" : null,
            $this->list('Unmet criteria', array_map(fn (CriterionData $criterion): string => "- `{$criterion->key}` ({$criterion->kind->value}): {$criterion->label}", $unmet)),
            $this->list('Evidence', $action->evidence->map(fn (Evidence $evidence): string => $this->evidenceLine($evidence))->all()),
            $this->list('Approvals', $action->approvals->map(fn ($approval): string => "- `{$approval->id}` {$approval->status->value}: ".str($approval->summary_md)->limit(120))->all()),
            "## Instructions\n".$this->renderFullPrompt->handle($action, withProtocol: false),
        ]);
    }

    public function actionLine(TaskAction $action): string
    {
        return "- [{$action->status->value}] {$action->title} (`{$action->id}`, {$action->type->value} via {$action->executor->value})"
            .($action->is_required ? '' : ' optional')
            .($action->requires_approval ? ' needs approval' : '');
    }

    private function evidenceLine(Evidence $evidence): string
    {
        $passed = $evidence->passed === null ? '' : ($evidence->passed ? ' ✓' : ' ✗');

        return "- {$evidence->kind->value}{$passed}: {$evidence->label}".($evidence->value ? " — {$evidence->value}" : '')
            .($evidence->criterion_key ? " (criterion `{$evidence->criterion_key}`)" : '');
    }

    /**
     * @return list<CriterionData>
     */
    private function criteria(Task $task): array
    {
        return array_map(fn (array $item): CriterionData => CriterionData::fromArray($item), $task->completion_criteria ?? []);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function list(string $heading, array $lines): ?string
    {
        return $lines === [] ? null : "## {$heading}\n".implode("\n", $lines);
    }

    /**
     * @param  array<int, string|null>  $sections
     */
    private function join(array $sections): string
    {
        return implode("\n\n", array_filter($sections, fn (?string $section): bool => $section !== null && trim($section) !== ''));
    }
}
