<?php

namespace App\Domain\Prompt\Actions;

use App\Domain\Task\Models\TaskAction;

/**
 * Short deep-link prompt (MCP_AND_SKILLS §4): IDs and skill names only, never company data.
 */
class RenderLauncherPrompt
{
    public const string PRODUCT_NAME = 'Founder OS';

    public const string RUNNER_SKILL = 'founder-os-task-runner';

    public function handle(TaskAction $action): string
    {
        $skills = implode(', ', $this->skillKeys($action));

        return implode("\n", [
            'Use the '.self::PRODUCT_NAME.' connector and the '.$skills.' skill'.(str_contains($skills, ',') ? 's' : '').'.',
            "Project: {$action->project_id} · Task: {$action->task_id} · Action: {$action->id}",
            '1. Call get_task for the task above and follow its instructions and skills.',
            '2. Follow the completion protocol. Ask me before anything irreversible.',
        ]);
    }

    /**
     * @return list<string>
     */
    public function skillKeys(TaskAction $action): array
    {
        $action->loadMissing('task.catalogTask.skills');

        /** @var list<string> $keys */
        $keys = $action->task->catalogTask?->skills->pluck('key')->all() ?? [];

        return array_values(array_unique([self::RUNNER_SKILL, ...$keys]));
    }
}
