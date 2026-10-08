<?php

namespace App\Ai\Agents;

use App\Ai\Support\SkillRepository;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasSkills;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Laravel\Ai\Skills\Skill;
use Stringable;

/**
 * Base for in-app agents that do one task action and answer with `output_md` + `outputs[]`.
 */
abstract class ActionAgent implements Agent, HasSkills, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $skillNames  skill folder names from `resources/skills`
     */
    public function __construct(protected array $skillNames = []) {}

    /**
     * The agent that runs the action: `config.agent` (draft|review|research), else by action type.
     *
     * @param  list<string>  $skillNames
     */
    public static function for(TaskAction $action, array $skillNames = []): self
    {
        $agent = $action->config['agent'] ?? match ($action->type) {
            ActionType::Research => 'research',
            ActionType::Check, ActionType::Approval => 'review',
            default => 'draft',
        };

        return match ($agent) {
            'research' => new ResearchSummaryAgent($skillNames),
            'review' => new ReviewAgent($skillNames),
            default => new DraftDocumentAgent($skillNames),
        };
    }

    /**
     * What this agent does, in a few sentences.
     */
    abstract protected function role(): string;

    public function instructions(): Stringable|string
    {
        return implode("\n\n", [
            $this->role(),
            'You work inside Founder OS on one action of the founder\'s project. The prompt holds the project context, the task and the action.',
            'Rules: use only facts from the prompt and loaded skills; mark guesses as assumptions. Write in clear, short Markdown. Do not invent URLs, names or numbers.',
            'Answer with `output_md` (the full result) and `outputs` (one entry per expected output: kind, label, value; `doc_type` for documents). Use an empty list when the task names no expected outputs.',
        ]);
    }

    public function model(): string
    {
        return (string) config('ai.models.default');
    }

    public function timeout(): int
    {
        return (int) config('ai.run_timeout');
    }

    /**
     * @return list<Skill>
     */
    public function skills(): iterable
    {
        return app(SkillRepository::class)->forAgents($this->skillNames);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'output_md' => $schema->string()->required(),
            'outputs' => $schema->array()->items($schema->object([
                'kind' => $schema->string()->enum(['document', 'value', 'url', 'file'])->required(),
                'label' => $schema->string()->required(),
                'value' => $schema->string()->required(),
                'doc_type' => $schema->string(),
            ]))->required(),
        ];
    }
}
