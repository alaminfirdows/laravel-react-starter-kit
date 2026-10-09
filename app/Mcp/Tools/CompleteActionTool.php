<?php

namespace App\Mcp\Tools;

use App\Domain\Task\Actions\AttachEvidence;
use App\Domain\Task\Actions\CompleteAction;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Mcp\Support\AgentEvidence;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('complete_action')]
/**
 * Evidence and completion share one transaction: a failed completion leaves no evidence, so a retry does not duplicate it.
 */
#[Description('Finish an action: store the result (Markdown) and evidence, then mark it done. Fails with the missing criteria if the action is not ready.')]
class CompleteActionTool extends Tool
{
    public function __construct(private CompleteAction $complete, private AttachEvidence $attach) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'action_id' => ['required', 'string'],
            'run_id' => ['nullable', 'string'],
            'output_md' => ['nullable', 'string', 'max:100000'],
            'evidence' => ['array', 'max:20'],
            ...AgentEvidence::rules('evidence.*.'),
        ]);
        $action = $mcp->action($validated['action_id'], 'update');

        try {
            return DB::transaction(function () use ($action, $mcp, $validated): Response {
                $action->newQueryWithoutScopes()->whereKey($action->getKey())->lockForUpdate()->value('id');
                $action->refresh();

                if (isset($validated['run_id']) && $validated['run_id'] !== $action->last_run_id) {
                    return Response::error('run_id is not the latest run of this action. Call get_action to see it.');
                }

                foreach ($validated['evidence'] ?? [] as $item) {
                    $this->attach->handle($action, AgentEvidence::toData($item), $mcp->actor());
                }

                $action = $this->complete->handle($action, $mcp->actor(), $validated['output_md'] ?? null);

                return Response::text("\"{$action->title}\" is {$action->status->value}. Call get_task to pick the next action.");
            });
        } catch (InvalidTaskTransition $e) {
            return Response::error($e->getMessage());
        }
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action_id' => $schema->string()->description('Action ID (ULID).')->required(),
            'run_id' => $schema->string()->description('run_id from start_action (optional check).'),
            'output_md' => $schema->string()->description('Final result in Markdown.'),
            'evidence' => $schema->array()->items($schema->object(AgentEvidence::properties($schema)))->description('Proof for the completion criteria.'),
        ];
    }
}
