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

#[Name('get_task')]
#[Description('Full task: goal, body, completion criteria, expected outputs, subtasks, actions (steps), skills, resources and evidence. Call this before working on a task.')]
#[IsReadOnly]
class GetTaskTool extends Tool
{
    public function __construct(private TaskMarkdown $markdown) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['task_id' => ['required', 'string']]);

        return Response::text($this->markdown->task($mcp->task($validated['task_id'])));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('Task ID (ULID) from the launcher prompt or list_tasks.')->required(),
        ];
    }
}
