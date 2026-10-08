<?php

namespace App\Domain\Workspace\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Concerns\GeneratesUniqueWorkspaceSlugs;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Enums\WorkspaceStatus;
use App\Domain\Workspace\Enums\WorkspaceType;
use App\Domain\Workspace\Policies\WorkspacePolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property WorkspaceType $type
 * @property WorkspaceStatus $status
 * @property string $owner_id
 * @property string|null $plan
 * @property array<string, mixed>|null $settings
 * @property string|null $logo_path
 * @property-read string|null $logo_url
 * @property-read WorkspaceMember $membership Pivot, set when loaded via User::workspaces()
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'slug', 'type', 'status', 'owner_id', 'plan', 'settings', 'logo_path'])]
#[UseFactory(WorkspaceFactory::class)]
#[UsePolicy(WorkspacePolicy::class)]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use GeneratesUniqueWorkspaceSlugs, HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'team',
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace): void {
            if (blank($workspace->slug)) {
                $workspace->slug = static::generateUniqueWorkspaceSlug($workspace->name);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WorkspaceType::class,
            'status' => WorkspaceStatus::class,
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this, WorkspaceMember, 'membership'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->using(WorkspaceMember::class)
            ->as('membership')
            ->withPivot(['id', 'role', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * Members who can work on projects (member and above): approvers and assignees.
     *
     * @return Builder<User>
     */
    public function editors(): Builder
    {
        return User::query()->whereIn('id', $this->memberships()
            ->whereIn('role', WorkspaceRole::atLeast(WorkspaceRole::Member))
            ->select('user_id'));
    }

    /**
     * @return HasMany<WorkspaceInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function isPersonal(): bool
    {
        return $this->type === WorkspaceType::Personal;
    }

    public function isActive(): bool
    {
        return $this->status === WorkspaceStatus::Active;
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null);
    }
}
