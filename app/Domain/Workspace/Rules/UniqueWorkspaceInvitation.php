<?php

namespace App\Domain\Workspace\Rules;

use App\Domain\Workspace\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Email is not a member yet and has no pending (unexpired) invitation.
 * Case-insensitive.
 */
class UniqueWorkspaceInvitation implements ValidationRule
{
    public function __construct(protected Workspace $workspace) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = Str::lower(trim((string) $value));

        $isMember = $this->workspace->members()
            ->whereRaw('lower(users.email) = ?', [$email])
            ->exists();

        if ($isMember) {
            $fail(__('This user is already a member of the workspace.'));

            return;
        }

        $hasPendingInvitation = $this->workspace->invitations()
            ->forEmail($email)
            ->pending()
            ->exists();

        if ($hasPendingInvitation) {
            $fail(__('An invitation has already been sent to this email address.'));
        }
    }
}
