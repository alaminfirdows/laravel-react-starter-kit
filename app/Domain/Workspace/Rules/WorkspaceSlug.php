<?php

namespace App\Domain\Workspace\Rules;

use App\Domain\Workspace\Support\ReservedWorkspaceSlugs;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Format + reserved-name check. Uniqueness is a separate unique rule.
 */
class WorkspaceSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $fail(__('The :attribute may only contain lowercase letters, numbers and single hyphens.'));

            return;
        }

        if (ReservedWorkspaceSlugs::contains($value)) {
            $fail(__('This :attribute is reserved and cannot be used.'));
        }
    }
}
