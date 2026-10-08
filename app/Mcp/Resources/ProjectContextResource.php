<?php

namespace App\Mcp\Resources;

use App\Mcp\Support\McpActor;
use App\Mcp\Support\ProjectMarkdown;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('project-context')]
#[Description('Project profile, market, goals, brand and progress as Markdown.')]
#[MimeType('text/markdown')]
class ProjectContextResource extends Resource implements HasUriTemplate
{
    public function __construct(private ProjectMarkdown $markdown) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('founder://projects/{project_id}/context');
    }

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['project_id' => ['required', 'string']]);

        return Response::text($this->markdown->context($mcp->project($validated['project_id']), ProjectMarkdown::SECTIONS));
    }
}
