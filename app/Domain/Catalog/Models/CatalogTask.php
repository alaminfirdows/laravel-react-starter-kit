<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Task\Enums\TaskPriority;
use Carbon\CarbonImmutable;
use Database\Factories\CatalogTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $key
 * @property int $category_id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $summary
 * @property string|null $body_md
 * @property TaskPriority $priority_default
 * @property array<int, array<string, mixed>>|null $completion_criteria
 * @property array<int, array<string, mixed>>|null $expected_outputs
 * @property int $version
 * @property CarbonImmutable|null $admin_edited_at not yet exported to YAML
 * @property CatalogStatus $status
 * @property CarbonImmutable|null $published_at
 * @property int $sort_order
 * @property-read CatalogCategory $category
 * @property-read Collection<int, Skill> $skills
 * @property-read Collection<int, CatalogResource> $resources
 * @property-read Pivot|null $pivot Set when loaded through `dependencies`
 */
#[Fillable(['key', 'category_id', 'parent_id', 'title', 'summary', 'body_md', 'body_doc', 'applicability', 'priority_default', 'est_minutes', 'difficulty', 'is_optional', 'completion_criteria', 'expected_outputs', 'version', 'content_hash', 'published_at', 'sort_order', 'admin_edited_at'])]
#[UseFactory(CatalogTaskFactory::class)]
class CatalogTask extends Model
{
    /** @use HasFactory<CatalogTaskFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'body_doc' => 'array',
            'applicability' => 'array',
            'completion_criteria' => 'array',
            'expected_outputs' => 'array',
            'is_optional' => 'boolean',
            'priority_default' => TaskPriority::class,
            'status' => CatalogStatus::class,
            'published_at' => 'immutable_datetime',
            'admin_edited_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<CatalogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class);
    }

    /** @return BelongsTo<CatalogTask, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<CatalogTask, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<CatalogAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(CatalogAction::class)->orderBy('sort_order');
    }

    /**
     * Scope nested `{catalogAction:key}` bindings to this task's actions.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        if ($childType === 'catalogAction') {
            return $this->actions()->where($field ?? 'key', $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    /** @return BelongsToMany<CatalogTask, $this> */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'catalog_task_dependencies', 'task_id', 'depends_on_id')
            ->withPivot('kind');
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'catalog_task_skill')->withPivot('required');
    }

    /** @return BelongsToMany<CatalogResource, $this> */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(CatalogResource::class, 'catalog_task_resource', 'catalog_task_id', 'resource_id')
            ->withPivot('sort_order', 'note')
            ->orderByPivot('sort_order');
    }
}
