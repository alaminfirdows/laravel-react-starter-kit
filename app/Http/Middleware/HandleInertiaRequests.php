<?php

namespace App\Http\Middleware;

use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public const int LATEST_NOTIFICATIONS = 8;

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            ...$this->workspaceProps($request),
            'notifications' => fn (): ?array => $request->user() ? [
                'unread' => $request->user()->unreadNotifications()->count(),
                'latest' => NotificationResource::collection($request->user()->unreadNotifications()->latest()->limit(self::LATEST_NOTIFICATIONS)->get()),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Workspace data for the sidebar switcher and settings pages.
     *
     * @return array<string, mixed>
     */
    protected function workspaceProps(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [
                'currentWorkspace' => null,
                'workspaces' => [],
                'workspacePermissions' => null,
                'pendingInvitationsCount' => 0,
            ];
        }

        // Resolved lazily: share() runs before DiscoverWorkspace sets the
        // workspace from the URL. Off tenant routes, use the user's last one.
        $workspace = fn () => currentWorkspace() ?? $user->currentWorkspace;

        return [
            'currentWorkspace' => fn () => ($ws = $workspace()) ? $user->toUserWorkspace($ws) : null,
            'workspaces' => fn () => $user->toUserWorkspaces(),
            'workspacePermissions' => fn () => ($ws = $workspace()) ? $user->toWorkspacePermissions($ws) : null,
            'pendingInvitationsCount' => fn () => WorkspaceInvitation::query()
                ->forEmail($user->email)
                ->pending()
                ->whereHas('workspace')
                ->count(),
            'paletteProjects' => Inertia::optional(fn (): array => ($ws = $workspace()) && $user->workspaceRole($ws) !== null
                ? $ws->projects()
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug'])
                    ->map(fn ($project): array => ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug])
                    ->all()
                : []),
        ];
    }
}
