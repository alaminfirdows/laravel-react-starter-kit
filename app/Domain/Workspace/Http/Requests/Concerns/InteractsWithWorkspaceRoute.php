<?php

namespace App\Domain\Workspace\Http\Requests\Concerns;

use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

/**
 * Typed access to the {workspace} and {member} route models.
 */
trait InteractsWithWorkspaceRoute
{
    public function workspace(): Workspace
    {
        $workspace = $this->route('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        return $workspace;
    }

    public function member(): User
    {
        $member = $this->route('member');

        if (! $member instanceof User) {
            abort(404);
        }

        return $member;
    }
}
