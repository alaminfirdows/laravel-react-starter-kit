<?php

namespace App\Providers;

use App\Mcp\Http\ApproveWorkspaceAuthorizationController;
use App\Mcp\Http\OAuthConsentView;
use App\Mcp\Http\WorkspaceAuthorizationController;
use App\Mcp\Support\McpScopeRepository;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Bridge\ScopeRepository;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::$deviceCodeGrantEnabled = false;

        $this->app->bind(ScopeRepository::class, McpScopeRepository::class);
        $this->app->bind(AuthorizationController::class, WorkspaceAuthorizationController::class);
        $this->app->bind(ApproveAuthorizationController::class, ApproveWorkspaceAuthorizationController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassport();

        Gate::define('admin', fn (User $user): bool => $user->is_admin);
    }

    /**
     * OAuth consent screen for the Claude connector, the MCP rate limit (120/min per token user)
     * and the client registration limit (10/hour per IP).
     * Container bindings (register) make every token carry a "workspace:<id>" scope picked on the consent screen.
     */
    protected function configurePassport(): void
    {
        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('oauth-register', fn (Request $request): Limit => Limit::perHour(10)->by((string) $request->ip()));

        Passport::authorizationView(app(OAuthConsentView::class)(...));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Inertia props: resources render flat, without a `data` key.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
