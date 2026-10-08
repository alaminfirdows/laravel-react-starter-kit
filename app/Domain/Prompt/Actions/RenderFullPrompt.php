<?php

namespace App\Domain\Prompt\Actions;

use App\Domain\Task\Models\TaskAction;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * Self-contained "full prompt" (PROJECT_CONTEXT §8) for users without the connector.
 * P1 adds the launcher prompt + deep link next to it.
 */
class RenderFullPrompt
{
    public const int MAX_CHARS = 14000;

    public const string COMPLETION_PROTOCOL = <<<'MD'
        ---
        When you finish:
        1. Check the result against the task goal above.
        2. List the outputs you produced.
        3. List any follow-up tasks.
        4. Paste the result back into Founder OS and mark the step done.
        MD;

    private const string DEFAULT_TEMPLATE = <<<'MD'
        You are helping the founder of {{ project.name }} — {{ project.one_liner }}.
        Business model: {{ project.business_model }}. Stage: {{ project.stage }}. Market: {{ project.primary_market }}.

        ## Task: {{ task.title }}
        {{ task.body_md }}

        ## Step: {{ action.title }}
        {{ action.instructions_md }}
        MD;

    private const array FIELDS = [
        'project' => ['name', 'one_liner', 'description_md', 'website_url', 'business_model', 'stage', 'industry', 'primary_market', 'target_customer', 'problem_statement', 'solution_summary', 'phase'],
        'task' => ['title', 'summary', 'body_md'],
        'action' => ['title', 'instructions_md'],
    ];

    public function handle(TaskAction $action): string
    {
        $action->loadMissing(['promptTemplate', 'task.project']);

        $template = $action->prompt_override_md
            ?? $action->promptTemplate->full_md
            ?? self::DEFAULT_TEMPLATE;

        $models = ['project' => $action->task->project, 'task' => $action->task, 'action' => $action];

        $body = preg_replace_callback('/\{\{\s*([a-z_]+)\.([a-z_]+)\s*\}\}/', function (array $m) use ($models): string {
            if (! in_array($m[2], self::FIELDS[$m[1]] ?? [], true)) {
                return '';
            }

            $value = $models[$m[1]]->getAttribute($m[2]);

            return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
        }, $template) ?? '';

        $prompt = preg_replace("/\n{3,}/", "\n\n", trim($body)."\n\n".self::COMPLETION_PROTOCOL) ?? '';

        return mb_strlen($prompt) > self::MAX_CHARS
            ? Str::substr($prompt, 0, self::MAX_CHARS - 13)."\n…[truncated]"
            : $prompt;
    }
}
