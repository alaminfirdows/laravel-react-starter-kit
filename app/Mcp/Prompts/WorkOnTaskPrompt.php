<?php

namespace App\Mcp\Prompts;

use App\Domain\Prompt\Actions\RenderLauncherPrompt;
use App\Domain\Task\Models\TaskAction;
use App\Mcp\Support\McpActor;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('run-task')]
#[Description('Start work on a Founder OS task: returns the launcher for its next open action.')]
class WorkOnTaskPrompt extends Prompt
{
    public function __construct(private RenderLauncherPrompt $launcher) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['task_id' => ['required', 'string']]);
        $task = $mcp->task($validated['task_id']);

        $action = $task->actions()->get()->first(fn (TaskAction $action): bool => ! $action->status->isClosed());

        if ($action === null) {
            return Response::error("\"{$task->title}\" has no open actions.");
        }

        return Response::text($this->launcher->handle($action->setRelation('task', $task)));
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('task_id', 'Task ID (ULID) from Founder OS.', required: true),
        ];
    }
}
