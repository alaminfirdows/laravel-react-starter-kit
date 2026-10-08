<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Str;

class UpdateWorkspace
{
    /**
     * Rename keeps the slug; the slug changes only when given explicitly.
     *
     * @param  array{name?: string, slug?: string}  $attributes
     */
    public function handle(Workspace $workspace, array $attributes): Workspace
    {
        if (isset($attributes['name'])) {
            $workspace->name = $attributes['name'];
        }

        if (isset($attributes['slug'])) {
            $workspace->slug = Str::lower($attributes['slug']);
        }

        $workspace->save();

        return $workspace;
    }
}
