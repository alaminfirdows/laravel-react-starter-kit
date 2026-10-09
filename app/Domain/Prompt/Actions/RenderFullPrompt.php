<?php

namespace App\Domain\Prompt\Actions;

use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Prompt\Support\TokenBudget;
use App\Domain\Task\Models\TaskAction;
use BackedEnum;
use WeakMap;

/**
 * Self-contained "full prompt" (PROJECT_CONTEXT §8) for users without the connector.
 * The launcher prompt + deep link live in RenderLauncherPrompt / BuildDeepLink.
 */
class RenderFullPrompt
{
    /** @var WeakMap<Project, array<string, string>>|null */
    private ?WeakMap $knowledge = null;

    public const int MAX_TOKENS = 3500;

    public const int MAX_CHARS = self::MAX_TOKENS * TokenBudget::CHARS_PER_TOKEN;

    /** Budget per `{{ knowledge.<doc_type> }}` placeholder. */
    public const int KNOWLEDGE_TOKENS = 1200;

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

    /**
     * @param  bool  $withProtocol  false for MCP, where the task-runner skill owns the completion protocol
     */
    public function handle(TaskAction $action, bool $withProtocol = true): string
    {
        $action->loadMissing(['promptTemplate', 'task.project', 'task.catalogTask.skills']);

        $template = $action->prompt_override_md
            ?? $action->promptTemplate->full_md
            ?? self::DEFAULT_TEMPLATE;

        $models = ['project' => $action->task->project, 'task' => $action->task, 'action' => $action];

        $body = preg_replace_callback('/\{\{\s*([a-z_]+)\.([a-z_]+)\s*\}\}/', function (array $m) use ($models): string {
            if ($m[1] === 'knowledge') {
                return $this->knowledge($models['project'], $m[2]);
            }

            if (! in_array($m[2], self::FIELDS[$m[1]] ?? [], true)) {
                return '';
            }

            $value = $models[$m[1]]->getAttribute($m[2]);

            return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
        }, $template) ?? '';

        $skills = $action->task->catalogTask?->skills->pluck('key')->implode(', ');
        $skillLine = $skills ? "\n\nIf you have the Founder OS skills installed, use: {$skills}." : '';

        $prompt = preg_replace("/\n{3,}/", "\n\n", trim($body).$skillLine.($withProtocol ? "\n\n".self::COMPLETION_PROTOCOL : '')) ?? '';

        return (new TokenBudget(self::MAX_TOKENS))->trim($prompt);
    }

    /**
     * Body of the project's approved document of that type, trimmed to its budget. Empty when none.
     */
    private function knowledge(Project $project, string $docType): string
    {
        $type = DocType::tryFrom($docType);

        if ($type === null) {
            return '';
        }

        $body = $this->approvedKnowledge($project)[$type->value] ?? null;

        return $body === null ? '' : (new TokenBudget(self::KNOWLEDGE_TOKENS))->trim(trim($body));
    }

    /**
     * Latest approved body per doc type, loaded once per project instance (not per placeholder or action).
     *
     * The memo lives as long as that Project instance and is never invalidated: a document approved
     * after the first render is not seen when the same instance is rendered again in the same
     * request or job. Load a fresh Project to re-read.
     *
     * @return array<string, string>
     */
    private function approvedKnowledge(Project $project): array
    {
        $this->knowledge ??= new WeakMap;

        return $this->knowledge[$project] ??= $project->knowledgeDocuments()
            ->where('status', DocStatus::Approved)
            ->latest('updated_at')
            ->get(['doc_type', 'body_md'])
            ->unique(fn (KnowledgeDocument $document): string => $document->doc_type->value)
            ->mapWithKeys(fn (KnowledgeDocument $document): array => [$document->doc_type->value => (string) $document->body_md])
            ->all();
    }
}
