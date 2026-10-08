<?php

namespace App\Mcp\Support;

use App\Domain\Activity\Data\Actor;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

/**
 * Who is calling an MCP tool: the token's user, the OAuth client (e.g. "Claude"),
 * and the workspace the call runs in. Resolving it also sets the current workspace,
 * so tenant models are scoped the same way as in the web app.
 */
final readonly class McpActor
{
    public const string WORKSPACE_SCOPE_PREFIX = 'workspace:';

    public const string DEFAULT_CLIENT_NAME = 'MCP client';

    public function __construct(
        public User $user,
        public Workspace $workspace,
        public WorkspaceRole $role,
        public string $clientName,
    ) {}

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public static function from(Request $request): self
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $token = $user->token();
        $workspace = self::workspaceFromToken($token) ?? $user->resolveCurrentWorkspace();
        $role = $workspace ? $user->workspaceRole($workspace) : null;

        if ($workspace === null || $role === null || ! $workspace->isActive()) {
            throw new AuthorizationException('This token has no access to a workspace.');
        }

        app(WorkspaceDiscoveryService::class)->setCurrentWorkspace($workspace);

        return new self($user, $workspace, $role, self::clientName($token));
    }

    public function actor(): Actor
    {
        return Actor::agent($this->user, $this->clientName);
    }

    /**
     * Project in the token's workspace the user may see (or edit), else "not found".
     *
     * @throws ValidationException
     */
    public function project(string $id, string $ability = 'view'): Project
    {
        $project = Project::query()->whereKey($id)->first();

        return $project && $this->user->can($ability, $project)
            ? $project
            : $this->notFound('project_id', 'Project');
    }

    /**
     * @throws ValidationException
     */
    public function task(string $id, string $ability = 'view'): Task
    {
        $task = Task::query()->whereKey($id)->first();

        return $task && $this->user->can($ability, $task)
            ? $task
            : $this->notFound('task_id', 'Task');
    }

    /**
     * @throws ValidationException
     */
    public function action(string $id, string $ability = 'view'): TaskAction
    {
        $action = TaskAction::query()->whereHas('task')->with('task')->whereKey($id)->first();

        return $action && $this->user->can($ability, $action->task)
            ? $action
            : $this->notFound('action_id', 'Action');
    }

    /**
     * Same answer for "missing" and "other workspace", so IDs leak nothing.
     */
    private function notFound(string $key, string $label): never
    {
        throw ValidationException::withMessages([$key => "{$label} not found."]);
    }

    private static function workspaceFromToken(mixed $token): ?Workspace
    {
        if (! $token instanceof AccessToken) {
            return null;
        }

        /** @var list<string> $scopes */
        $scopes = $token->oauth_scopes ?? [];

        foreach ($scopes as $scope) {
            if (Str::startsWith($scope, self::WORKSPACE_SCOPE_PREFIX)) {
                return Workspace::query()->whereKey(Str::after($scope, self::WORKSPACE_SCOPE_PREFIX))->first()
                    ?? throw new AuthorizationException('Unknown workspace in token scope.');
            }
        }

        return null;
    }

    private static function clientName(mixed $token): string
    {
        $clientId = $token instanceof AccessToken ? $token->oauth_client_id : null;

        if ($clientId === null) {
            return self::DEFAULT_CLIENT_NAME;
        }

        $name = Passport::client()->newQuery()->whereKey($clientId)->value('name');

        return is_string($name) && $name !== '' ? $name : self::DEFAULT_CLIENT_NAME;
    }
}
