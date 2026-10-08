<?php

namespace App\Domain\Workspace\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Workspace slugs live at the URL root (/{workspace}/...), so a slug must
 * never match a top-level path used by the app.
 */
class ReservedWorkspaceSlugs
{
    /**
     * @var list<string>
     */
    protected const array STATIC = [
        'admin', 'api', 'app', 'assets', 'auth', 'billing', 'build', 'dashboard',
        'default', 'docs', 'email', 'forgot-password', 'help', 'home', 'invitations',
        'login', 'logout', 'mcp', 'new', 'oauth', 'register', 'reset-password',
        'settings', 'static', 'storage', 'support', 'system', 'two-factor-challenge',
        'up', 'user', 'users', 'verify-email', 'workspace', 'workspaces', 'www',
    ];

    public static function contains(string $slug): bool
    {
        $slug = Str::lower(trim($slug));

        return in_array($slug, static::all(), true) || ctype_digit($slug);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique([...self::STATIC, ...static::routePrefixes()]));
    }

    /**
     * First URI segment of every registered route that is not a parameter.
     *
     * @return list<string>
     */
    public static function routePrefixes(): array
    {
        $prefixes = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $segment = Str::lower(Str::before(ltrim($route->uri(), '/'), '/'));

            if ($segment !== '' && ! str_starts_with($segment, '{')) {
                $prefixes[] = $segment;
            }
        }

        return array_values(array_unique($prefixes));
    }
}
