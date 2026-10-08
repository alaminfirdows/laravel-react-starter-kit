<?php

namespace App\Mcp\Tools;

use App\Domain\Task\Actions\SaveRunOutput;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('save_output')]
#[Description('Save a draft result (Markdown) on the running action without completing it.')]
class SaveOutputTool extends Tool
{
    public function __construct(private SaveRunOutput $save) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'action_id' => ['required', 'string'],
            'output_md' => ['required', 'string', 'max:100000'],
        ]);
        $action = $mcp->action($validated['action_id'], 'update');

        try {
            $run = $this->save->handle($action, $mcp->actor(), $validated['output_md']);
        } catch (InvalidTaskTransition $e) {
            return Response::error($e->getMessage());
        }

        return Response::text("Output saved on run {$run->id}.");
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action_id' => $schema->string()->description('Action ID (ULID) of a started action.')->required(),
            'output_md' => $schema->string()->description('The result so far, in Markdown.')->required(),
        ];
    }
}
