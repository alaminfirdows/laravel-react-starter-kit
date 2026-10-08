<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Enums\WorkspaceType;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace
{
    public function handle(User $user, string $name, WorkspaceType $type = WorkspaceType::Team): Workspace
    {
        return DB::transaction(function () use ($user, $name, $type): Workspace {
            $workspace = Workspace::create([
                'name' => $name,
                'type' => $type,
                'owner_id' => $user->id,
            ]);

            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
                'joined_at' => now(),
            ]);

            $user->switchWorkspace($workspace);

            return $workspace;
        });
    }

    public function personal(User $user): Workspace
    {
        $firstName = Str::of($user->name)->trim()->before(' ')->toString();

        return $this->handle(
            $user,
            $firstName === '' ? 'Personal Workspace' : "{$firstName}'s Workspace",
            WorkspaceType::Personal,
        );
    }
}
