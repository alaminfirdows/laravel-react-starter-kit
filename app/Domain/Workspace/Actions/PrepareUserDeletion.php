<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PrepareUserDeletion
{
    /**
     * Block account deletion while the user owns a shared workspace with
     * other members. Owned workspaces are removed by the owner_id cascade.
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $errorKey = 'password'): void
    {
        $blocking = $user->ownedWorkspaces()
            ->whereHas('memberships', fn ($query) => $query->where('user_id', '!=', $user->id))
            ->pluck('name');

        if ($blocking->isNotEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => __('Transfer ownership or delete these workspaces first: :names.', [
                    'names' => $blocking->implode(', '),
                ]),
            ]);
        }

        $user->ownedWorkspaces()->withTrashed()->each(function (Workspace $workspace): void {
            if ($workspace->logo_path) {
                app(UpdateWorkspaceLogo::class)->remove($workspace);
            }
        });
    }
}
