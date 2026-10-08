<?php

namespace App\Domain\Workspace\Data;

/**
 * Frontend-ready view of a workspace from one user's point of view.
 */
readonly class UserWorkspace
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool $isPersonal,
        public string $status,
        public ?string $logoUrl,
        public ?string $role,
        public ?string $roleLabel,
        public bool $isCurrent = false,
    ) {}
}
