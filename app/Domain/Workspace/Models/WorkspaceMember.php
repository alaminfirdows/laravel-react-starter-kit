<?php

namespace App\Domain\Workspace\Models;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\WorkspaceMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property string $workspace_id
 * @property string $user_id
 * @property WorkspaceRole $role
 * @property string|null $invited_by
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['workspace_id', 'user_id', 'role', 'invited_by', 'joined_at'])]
#[UseFactory(WorkspaceMemberFactory::class)]
class WorkspaceMember extends Pivot
{
    /** @use HasFactory<WorkspaceMemberFactory> */
    use HasFactory, HasUlids;

    protected $table = 'workspace_members';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
