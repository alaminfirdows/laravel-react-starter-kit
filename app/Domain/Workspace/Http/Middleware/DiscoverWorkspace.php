<?php

namespace App\Domain\Workspace\Http\Middleware;

use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves {workspace} from the URL and makes it the current workspace.
 *
 * Runs before SubstituteBindings (see bootstrap/app.php), so tenant models
 * bound in the same route are already scoped to this workspace.
 *
 * Usage: ->middleware(DiscoverWorkspace::class) or 'workspace:admin'.
 */
class DiscoverWorkspace
{
    public const string ROLE_ATTRIBUTE = 'workspace_role';

    public function __construct(protected WorkspaceDiscoveryService $context) {}

    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        /** @var User $user */
        $user = $request->user();

        $workspace = $this->workspace($request) ?? abort(404);
        $role = $user->workspaceRole($workspace);

        if ($role === null) {
            return $this->denyAccess($request, $user, $workspace);
        }

        if ($minimumRole !== null && ! $role->isAtLeast(WorkspaceRole::from($minimumRole))) {
            abort(403);
        }

        $request->route()?->setParameter('workspace', $workspace);
        $request->attributes->set(self::ROLE_ATTRIBUTE, $role);

        $this->context->setCurrentWorkspace($workspace);
        URL::defaults(['workspace' => $workspace->slug]);

        if (! $user->isCurrentWorkspace($workspace)) {
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        $user->setRelation('currentWorkspace', $workspace);

        if (! $workspace->isActive()) {
            return Inertia::render('workspace/suspended', [
                'status' => $workspace->status->value,
                'statusLabel' => $workspace->status->label(),
            ])->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }

    protected function workspace(Request $request): ?Workspace
    {
        $parameter = $request->route('workspace');

        if ($parameter instanceof Workspace) {
            return $parameter;
        }

        return is_string($parameter)
            ? Workspace::query()->where('slug', $parameter)->first()
            : null;
    }

    /**
     * Not a member (never was, or was removed). For page visits, send the
     * user to a workspace they can use; for writes, 403.
     */
    protected function denyAccess(Request $request, User $user, Workspace $workspace): Response
    {
        if (! $request->isMethod('GET')) {
            abort(403);
        }

        // Also clears current_workspace_id if it pointed at this workspace.
        $fallback = $user->resolveCurrentWorkspace();

        Inertia::flash('toast', ['type' => 'error', 'message' => __('You do not have access to that workspace.')]);

        return $fallback
            ? redirect()->route('dashboard', ['workspace' => $fallback->slug])
            : redirect()->route('workspaces.index');
    }
}
