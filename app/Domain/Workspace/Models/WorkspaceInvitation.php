<?php

namespace App\Domain\Workspace\Models;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\WorkspaceInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $workspace_id
 * @property string $code
 * @property string $email
 * @property WorkspaceRole $role
 * @property string|null $invited_by
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['email', 'role', 'invited_by', 'expires_at', 'accepted_at'])]
#[UseFactory(WorkspaceInvitationFactory::class)]
class WorkspaceInvitation extends Model
{
    /** @use HasFactory<WorkspaceInvitationFactory> */
    use HasFactory, HasUlids, MassPrunable;

    public const int EXPIRES_IN_DAYS = 3;

    public const int PRUNE_AFTER_DAYS = 30;

    protected static function booted(): void
    {
        static::creating(function (WorkspaceInvitation $invitation): void {
            $invitation->email = Str::lower(trim($invitation->email));
            $invitation->code ??= static::generateCode();
            $invitation->expires_at ??= now()->addDays(self::EXPIRES_IN_DAYS);
        });
    }

    public static function generateCode(): string
    {
        return Str::random(64);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function isFor(User $user): bool
    {
        return Str::lower($user->email) === $this->email;
    }

    /**
     * Not accepted and not expired.
     *
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForEmail(Builder $query, string $email): void
    {
        $query->where('email', Str::lower(trim($email)));
    }

    /**
     * Accepted or expired invitations older than the retention window.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays(self::PRUNE_AFTER_DAYS);

        return static::query()->where(fn (Builder $query) => $query
            ->where('accepted_at', '<', $cutoff)
            ->orWhere('expires_at', '<', $cutoff));
    }
}
