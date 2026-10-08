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

#[Name('whoami')]
#[Description('Show the connected account, its workspace and role, and the projects in that workspace.')]
#[IsReadOnly]
class WhoAmITool extends Tool
{
    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);

        $projects = Project::query()->orderBy('name')->get(['id', 'name', 'phase'])
            ->map(fn (Project $project): string => "- {$project->name} (`{$project->id}`, {$project->phase->value})")
            ->implode("\n");

        return Response::text(implode("\n", [
            "User: {$mcp->user->name} <{$mcp->user->email}>",
            "Workspace: {$mcp->workspace->name} (`{$mcp->workspace->id}`), role: {$mcp->role->value}",
            "Client: {$mcp->clientName}",
            '',
            '## Projects',
            $projects !== '' ? $projects : '_No projects yet._',
        ]));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
