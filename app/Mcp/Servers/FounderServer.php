<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetActionTool;
use App\Mcp\Tools\GetProjectContextTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\WhoAmITool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Founder OS')]
#[Version('1.0.0')]
#[Instructions('Always call get_task before working on a task. Never mark an action complete without evidence when its criteria require it. Use request_approval before irreversible actions.')]
class FounderServer extends Server
{
    protected array $tools = [
        WhoAmITool::class,
        ListProjectsTool::class,
        GetProjectContextTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        GetActionTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
