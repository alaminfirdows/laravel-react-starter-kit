<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpActor;
use App\Mcp\Support\ProjectMarkdown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_project_context')]
#[Description('Company context for a project: profile, market, goals, brand voice and progress. Read it before drafting anything for the founder.')]
#[IsReadOnly]
class GetProjectContextTool extends Tool
{
    public function __construct(private ProjectMarkdown $markdown) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', 'in:'.implode(',', ProjectMarkdown::SECTIONS)],
        ]);

        return Response::text($this->markdown->context($mcp->project($validated['project_id']), $validated['sections'] ?? ProjectMarkdown::SECTIONS));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (ULID) from list_projects or the launcher prompt.')->required(),
            'sections' => $schema->array()->items($schema->string()->enum(ProjectMarkdown::SECTIONS))->description('Limit the output to these sections. Default: all.'),
        ];
    }
}
