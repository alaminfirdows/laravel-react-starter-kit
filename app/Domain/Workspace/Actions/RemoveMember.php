<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Task\Actions\AssignTask;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Mcp\Actions\RevokeMcpConnection;
use App\Mcp\Queries\ActiveMcpConnections;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;

class RemoveMember
{
    public function __construct(
        protected AssignTask $assign,
        protected ActiveMcpConnections $connections,
        protected RevokeMcpConnection $revoke,
        protected ActivityRecorder $activity,
    ) {}

    /**
     * Also used for "leave". The owner can never be removed. Their tasks become unassigned and their MCP connections to it are revoked.
     */
    public function handle(Workspace $workspace, User $member): void
    {
        DB::transaction(function () use ($workspace, $member): void {
            $membership = $workspace->memberships()
                ->where('user_id', $member->id)
                ->where('role', '!=', WorkspaceRole::Owner->value)
                ->lockForUpdate()
                ->firstOrFail();
            $membership->delete();

            $this->activity->record('workspace.member_removed', $workspace, [
                'user_id' => $member->id,
                'role' => $membership->role->value,
            ], Actor::current());

            Task::withoutWorkspaceScope()
                ->where('workspace_id', $workspace->id)
                ->where('assignee_id', $member->id)
                ->get()
                ->each(fn (Task $task) => $this->assign->handle($task, null, Actor::current()));

            $this->connections->workspaceTokens($workspace, $member)->get()
                ->each(fn (Token $token) => $this->revoke->handle($token, Actor::current()));

            if ($member->isCurrentWorkspace($workspace)) {
                $member->forceFill(['current_workspace_id' => $member->fallbackWorkspace($workspace)?->id])->save();
            }
        });
    }
}
