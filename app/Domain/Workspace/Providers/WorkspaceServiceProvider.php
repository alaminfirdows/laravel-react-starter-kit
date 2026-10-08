<?php

namespace App\Domain\Workspace\Providers;

use App\Domain\Workspace\Console\SetWorkspaceStatus;
use App\Domain\Workspace\Contracts\WorkspaceDiscoveryService;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Queue\WorkspaceQueueContext;
use App\Domain\Workspace\Services\WorkspaceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class WorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: reset per request / per queue job (Octane safe).
        $this->app->scoped(WorkspaceDiscoveryService::class, WorkspaceContext::class);
        $this->app->singleton(WorkspaceQueueContext::class);
    }

    public function boot(): void
    {
        $this->app->make(WorkspaceQueueContext::class)->register($this->app->make(Dispatcher::class));

        RateLimiter::for('workspace-invitations', function (Request $request): Limit {
            // Runs before DiscoverWorkspace: {workspace} can be a slug or a model.
            $workspace = $request->route('workspace');
            $key = $workspace instanceof Workspace ? $workspace->slug : (string) $workspace;

            return Limit::perHour(20)->by('workspace-invitations:'.$key);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SetWorkspaceStatus::class]);
        }
    }
}
