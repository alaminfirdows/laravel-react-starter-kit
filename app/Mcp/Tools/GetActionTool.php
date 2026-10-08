<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpActor;
use App\Mcp\Support\TaskMarkdown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_action')]
#[Description('One step of a task: rendered instructions with company context, unmet completion criteria, last run, evidence and approvals.')]
#[IsReadOnly]
class GetActionTool extends Tool
{
    public function __construct(private TaskMarkdown $markdown) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['action_id' => ['required', 'string']]);

        return Response::text($this->markdown->action($mcp->action($validated['action_id'])));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action_id' => $schema->string()->description('Action ID (ULID) from the launcher prompt or get_task.')->required(),
        ];
    }
}
