<?php

namespace App\Domain\Workspace\Http\Resources;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A workspace member as seen by the acting user.
 *
 * @mixin User
 */
class MemberResource extends JsonResource
{
    public function __construct(
        User $member,
        private readonly User $actor,
        private readonly ?WorkspaceRole $actorRole,
        private readonly bool $canUpdateAny,
        private readonly bool $canRemoveAny,
    ) {
        parent::__construct($member);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->membership->role;
        $outranked = ! $this->actor->is($this->resource) && $this->actorRole?->outranks($role) === true;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role->value,
            'roleLabel' => $role->label(),
            'joinedAt' => $this->membership->joined_at?->toIso8601String(),
            'isCurrentUser' => $this->actor->is($this->resource),
            'canUpdate' => $this->canUpdateAny && $outranked,
            'canRemove' => $this->canRemoveAny && $outranked,
        ];
    }
}
