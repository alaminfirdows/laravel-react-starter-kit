<?php

namespace App\Mcp\Tools;

use App\Domain\Prompt\Actions\RenderFullPrompt;
use App\Domain\Task\Actions\StartAction;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('start_action')]
#[Description('Mark an action as running before you work on it. Returns the run_id. Call complete_action when done.')]
class StartActionTool extends Tool
{
    public function __construct(private StartAction $start, private RenderFullPrompt $prompt) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['action_id' => ['required', 'string']]);
        $action = $mcp->action($validated['action_id'], 'update');

        try {
            $run = $this->start->handle($action, $mcp->actor(), RunChannel::Mcp, $this->prompt->handle($action, withProtocol: false));
        } catch (InvalidTaskTransition $e) {
            return Response::error($e->getMessage());
        }

        return Response::text("Started \"{$action->title}\".\nrun_id: {$run->id}\nWhen done, call complete_action with output_md and evidence.");
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
