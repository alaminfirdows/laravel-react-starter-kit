<?php

namespace App\Mcp\Tools;

use App\Domain\Project\Actions\BuildContextSnapshot;
use App\Domain\Project\Support\ProjectMarkdown;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_project_context')]
#[Description('Company context for a project: profile, market, goals, brand voice, approved documents (ICP, positioning, …), recent decisions and progress. Read it before drafting anything for the founder.')]
#[IsReadOnly]
class GetProjectContextTool extends Tool
{
    public function __construct(private ProjectMarkdown $markdown, private BuildContextSnapshot $snapshot) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', 'in:'.implode(',', ProjectMarkdown::SECTIONS)],
        ]);

        $project = $mcp->project($validated['project_id']);

        if (isset($validated['sections'])) {
            return Response::text($this->markdown->context($project, $validated['sections']));
        }

        return Response::text($this->snapshot->current($project)."\n\n".$this->markdown->progress($project));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (ULID) from list_projects or the launcher prompt.')->required(),
            'sections' => $schema->array()->items($schema->string()->enum(ProjectMarkdown::SECTIONS))->description('Only these profile sections, without documents and decisions. Default: full context snapshot.'),
        ];
    }
}
