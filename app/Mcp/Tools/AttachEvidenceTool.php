<?php

namespace App\Mcp\Tools;

use App\Domain\Task\Actions\AttachEvidence;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Mcp\Support\AgentEvidence;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('attach_evidence')]
#[Description('Attach proof to an action (a URL, a value or a note), optionally for one completion criterion.')]
class AttachEvidenceTool extends Tool
{
    public function __construct(private AttachEvidence $attach) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['action_id' => ['required', 'string'], ...AgentEvidence::rules()]);
        $action = $mcp->action($validated['action_id'], 'update');

        try {
            $evidence = $this->attach->handle($action, AgentEvidence::toData($validated), $mcp->actor());
        } catch (InvalidTaskTransition $e) {
            return Response::error($e->getMessage());
        }

        return Response::text("Evidence \"{$evidence->label}\" attached to \"{$action->title}\".");
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action_id' => $schema->string()->description('Action ID (ULID).')->required(),
            ...AgentEvidence::properties($schema),
        ];
    }
}
