<?php

namespace App\Mcp\Resources;

use App\Mcp\Support\McpActor;
use App\Mcp\Support\TaskMarkdown;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('task')]
#[Description('A task with criteria, actions, skills, resources and evidence as Markdown (same as get_task).')]
#[MimeType('text/markdown')]
class TaskResource extends Resource implements HasUriTemplate
{
    public function __construct(private TaskMarkdown $markdown) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('founder://tasks/{task_id}');
    }

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['task_id' => ['required', 'string']]);

        return Response::text($this->markdown->task($mcp->task($validated['task_id'])));
    }
}
