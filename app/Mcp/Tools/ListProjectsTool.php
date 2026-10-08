<?php

namespace App\Mcp\Tools;

use App\Domain\Project\Models\Project;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('List the projects in the connected workspace with phase, status and progress.')]
#[IsReadOnly]
class ListProjectsTool extends Tool
{
    public function handle(Request $request): Response
    {
        McpActor::from($request);

        $lines = Project::query()->orderBy('name')->limit(100)->get()
            ->map(fn (Project $project): string => "- {$project->name} (`{$project->id}`) · phase {$project->phase->value} · {$project->status->value} · {$project->progress_pct}%"
                .($project->one_liner ? " — {$project->one_liner}" : ''));

        return Response::text($lines->isEmpty() ? '_No projects yet._' : "# Projects\n".$lines->implode("\n"));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
