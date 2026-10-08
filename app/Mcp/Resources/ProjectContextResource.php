<?php

namespace App\Mcp\Resources;

use App\Domain\Project\Actions\BuildContextSnapshot;
use App\Domain\Project\Support\ProjectMarkdown;
use App\Mcp\Support\McpActor;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('project-context')]
#[Description('Project context snapshot (profile, approved documents, decisions) and progress as Markdown.')]
#[MimeType('text/markdown')]
class ProjectContextResource extends Resource implements HasUriTemplate
{
    public function __construct(private ProjectMarkdown $markdown, private BuildContextSnapshot $snapshot) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('founder://projects/{project_id}/context');
    }

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['project_id' => ['required', 'string']]);

        $project = $mcp->project($validated['project_id']);

        return Response::text($this->snapshot->current($project)."\n\n".$this->markdown->progress($project));
    }
}
