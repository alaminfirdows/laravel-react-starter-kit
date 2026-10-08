<?php

namespace App\Mcp\Tools;

use App\Domain\Task\Actions\RequestApproval;
use App\Domain\Task\Exceptions\InvalidTaskTransition;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('request_approval')]
#[Description('Ask the founder to approve the result of an action. Only a person can approve; stop and tell the user to review it in Founder OS.')]
class RequestApprovalTool extends Tool
{
    public function __construct(private RequestApproval $request) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'action_id' => ['required', 'string'],
            'summary_md' => ['required', 'string', 'max:20000'],
        ]);
        $action = $mcp->action($validated['action_id'], 'update');

        try {
            $approval = $this->request->handle($action, $mcp->actor(), $validated['summary_md']);
        } catch (InvalidTaskTransition $e) {
            return Response::error($e->getMessage());
        }

        return Response::text("Approval {$approval->id} requested for \"{$action->title}\". Ask the user to approve it in Founder OS, then call complete_action.");
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action_id' => $schema->string()->description('Action ID (ULID).')->required(),
            'summary_md' => $schema->string()->description('What to approve and why, in Markdown.')->required(),
        ];
    }
}
