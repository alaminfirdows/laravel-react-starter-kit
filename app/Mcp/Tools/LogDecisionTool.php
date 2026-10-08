<?php

namespace App\Mcp\Tools;

use App\Domain\Knowledge\Actions\RecordDecision;
use App\Domain\Knowledge\Data\DecisionData;
use App\Domain\Knowledge\Enums\DocSource;
use App\Mcp\Support\McpActor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('log_decision')]
#[Description('Record a decision the founder made (what, why, alternatives) in the project decision log.')]
class LogDecisionTool extends Tool
{
    public function __construct(private RecordDecision $record) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'decision_md' => ['required', 'string', 'max:20000'],
            'rationale_md' => ['nullable', 'string', 'max:20000'],
            'alternatives' => ['nullable', 'array', 'max:10'],
            'alternatives.*' => ['string', 'max:500'],
            'task_id' => ['nullable', 'string'],
            'revisit_on' => ['nullable', 'date', 'after:today'],
        ]);

        $project = $mcp->project($validated['project_id'], 'update');
        $task = isset($validated['task_id']) ? $mcp->task($validated['task_id']) : null;

        if ($task !== null && $task->project_id !== $project->id) {
            throw ValidationException::withMessages(['task_id' => 'Task must belong to this project.']);
        }

        $decision = $this->record->handle($project, new DecisionData(
            title: $validated['title'],
            decisionMd: $validated['decision_md'],
            rationaleMd: $validated['rationale_md'] ?? null,
            alternatives: isset($validated['alternatives']) ? array_values($validated['alternatives']) : null,
            revisitOn: isset($validated['revisit_on']) ? CarbonImmutable::parse($validated['revisit_on']) : null,
            taskId: $task?->id,
            source: DocSource::ClaudeMcp,
        ), $mcp->actor());

        return Response::text("Decision `{$decision->id}` recorded.");
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (ULID).')->required(),
            'title' => $schema->string()->description('Short decision title, e.g. "Charge monthly only".')->required(),
            'decision_md' => $schema->string()->description('What was decided, in Markdown.')->required(),
            'rationale_md' => $schema->string()->description('Why.'),
            'alternatives' => $schema->array()->items($schema->string())->description('Options that were rejected.'),
            'task_id' => $schema->string()->description('Task the decision belongs to.'),
            'revisit_on' => $schema->string()->format('date')->description('Date (YYYY-MM-DD) to review the decision again.'),
        ];
    }
}
