<?php

namespace App\Domain\Workspace\Concerns;

use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Support\ReservedWorkspaceSlugs;
use Illuminate\Support\Str;

trait GeneratesUniqueWorkspaceSlugs
{
    /**
     * Slugify the name and add a numeric suffix until the slug is free.
     * Soft-deleted workspaces keep their slug reserved.
     */
    public static function generateUniqueWorkspaceSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($name), 48, '');
        $base = trim($base, '-');

        if ($base === '' || ReservedWorkspaceSlugs::contains($base)) {
            $base = $base === '' ? 'workspace' : "{$base}-workspace";
        }

        $slug = $base;
        $suffix = 2;

        while (static::workspaceSlugIsTaken($slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected static function workspaceSlugIsTaken(string $slug, ?string $ignoreId): bool
    {
        return ReservedWorkspaceSlugs::contains($slug)
            || Workspace::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();
    }
}
