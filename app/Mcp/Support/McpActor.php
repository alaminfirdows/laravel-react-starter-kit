<?php

namespace App\Mcp\Support;

use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Models\KnowledgeDocument;
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
 * and the workspace the call runs in. The workspace comes only from the token's "workspace:<id>" scope,
 * never from the web UI's current workspace. Resolving it also sets the current workspace,
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
        $workspace = self::workspaceFromToken($token);

        if ($workspace === null) {
            throw new AuthorizationException('This token is not bound to exactly one workspace. Reconnect the app to pick one.');
        }

        $role = $user->workspaceRole($workspace);

        if ($role === null || ! $workspace->isActive()) {
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
     * @throws AuthorizationException
     */
    public function project(string $id, string $ability = 'view'): Project
    {
        $project = Project::query()->whereKey($id)->first();

        if ($project === null || ! $this->user->can('view', $project)) {
            $this->notFound('project_id', 'Project');
        }

        $this->ensureAllowed($ability, $project, 'project');

        return $project;
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function task(string $id, string $ability = 'view'): Task
    {
        $task = Task::query()->whereKey($id)->first();

        if ($task === null || ! $this->user->can('view', $task)) {
            $this->notFound('task_id', 'Task');
        }

        $this->ensureAllowed($ability, $task, 'task');

        return $task;
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function action(string $id, string $ability = 'view'): TaskAction
    {
        $action = TaskAction::query()->whereHas('task')->with('task')->whereKey($id)->first();

        if ($action === null || ! $this->user->can('view', $action->task)) {
            $this->notFound('action_id', 'Action');
        }

        $this->ensureAllowed($ability, $action->task, 'action');

        return $action;
    }

    /**
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function document(string $id, string $ability = 'view'): KnowledgeDocument
    {
        $document = KnowledgeDocument::query()->with('project')->whereKey($id)->first();

        if ($document === null || ! $this->user->can('view', $document->project)) {
            $this->notFound('document_id', 'Document');
        }

        $this->ensureAllowed($ability, $document->project, 'document');

        return $document;
    }

    /**
     * Visible but not editable (e.g. a Viewer): say so plainly instead of "not found".
     *
     * @throws AuthorizationException
     */
    private function ensureAllowed(string $ability, Project|Task $subject, string $label): void
    {
        if (! $this->user->can($ability, $subject)) {
            throw new AuthorizationException("You do not have permission to change this {$label}.");
        }
    }

    /**
     * Same answer for "missing" and "other workspace", so IDs leak nothing.
     */
    private function notFound(string $key, string $label): never
    {
        throw ValidationException::withMessages([$key => "{$label} not found."]);
    }

    /**
     * The token's one workspace. No workspace scope, or more than one, binds to nothing.
     *
     * @throws AuthorizationException
     */
    private static function workspaceFromToken(mixed $token): ?Workspace
    {
        if (! $token instanceof AccessToken) {
            return null;
        }

        /** @var list<string> $scopes */
        $scopes = $token->oauth_scopes ?? [];
        $workspaceIds = self::workspaceIds($scopes);

        if (count($workspaceIds) !== 1) {
            return null;
        }

        return Workspace::query()->whereKey($workspaceIds[0])->first()
            ?? throw new AuthorizationException('Unknown workspace in token scope.');
    }

    /**
     * @param  array<int, string>  $scopes
     * @return list<string>
     */
    public static function workspaceIds(array $scopes): array
    {
        return array_values(array_map(
            fn (string $scope): string => Str::after($scope, self::WORKSPACE_SCOPE_PREFIX),
            array_filter($scopes, fn (string $scope): bool => Str::startsWith($scope, self::WORKSPACE_SCOPE_PREFIX)),
        ));
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
