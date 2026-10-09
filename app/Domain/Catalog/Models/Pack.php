<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Enums\PackReviewStatus;
use App\Domain\Catalog\Enums\PackVisibility;
use App\Domain\Catalog\Policies\PackPolicy;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Workspace\Models\Workspace;
use Carbon\CarbonImmutable;
use Database\Factories\PackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description_md
 * @property array<string, mixed>|null $audience
 * @property bool $is_default
 * @property int $version
 * @property CarbonImmutable|null $admin_edited_at not yet exported to YAML
 * @property string|null $content_hash
 * @property CatalogStatus $status
 * @property string|null $owner_workspace_id community pack owner; null for official packs
 * @property PackVisibility $visibility
 * @property PackReviewStatus|null $review_status
 * @property string|null $review_note
 * @property-read Workspace|null $ownerWorkspace
 */
#[Fillable(['key', 'name', 'description_md', 'audience', 'is_default', 'version', 'content_hash', 'status', 'admin_edited_at'])]
#[UsePolicy(PackPolicy::class)]
#[UseFactory(PackFactory::class)]
class Pack extends Model
{
    /** @use HasFactory<PackFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'is_default' => 'boolean',
            'status' => CatalogStatus::class,
            'admin_edited_at' => 'immutable_datetime',
            'visibility' => PackVisibility::class,
            'review_status' => PackReviewStatus::class,
        ];
    }

    /**
     * Official catalog packs (no owner workspace).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function official(Builder $query): void
    {
        $query->whereNull('owner_workspace_id');
    }

    /**
     * Packs a workspace may see: published official or reviewed public
     * community packs, plus every pack the workspace owns.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, Workspace $workspace): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('owner_workspace_id', $workspace->id)
            ->orWhere(fn (Builder $query) => $query
                ->where('status', CatalogStatus::Published)
                ->where(fn (Builder $query) => $query
                    ->whereNull('owner_workspace_id')
                    ->orWhere('visibility', PackVisibility::Public))));
    }

    public static function defaultForPhase(ProjectPhase $phase): ?self
    {
        return static::query()
            ->where('status', CatalogStatus::Published)
            ->where('is_default', true)
            ->where('audience->phase', $phase->value)
            ->orderByDesc('version')
            ->first();
    }

    /** @return BelongsTo<Workspace, $this> */
    public function ownerWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'owner_workspace_id');
    }

    /** @return HasMany<PackItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PackItem::class)->orderBy('sort_order');
    }
}
