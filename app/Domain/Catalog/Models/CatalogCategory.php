<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CatalogPhase;
use Database\Factories\CatalogCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description_md
 * @property CatalogPhase $phase
 * @property string|null $icon
 * @property int $sort_order
 */
#[Fillable(['key', 'name', 'description_md', 'phase', 'icon', 'sort_order'])]
#[UseFactory(CatalogCategoryFactory::class)]
class CatalogCategory extends Model
{
    /** @use HasFactory<CatalogCategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'phase' => CatalogPhase::class,
        ];
    }

    /** @return HasMany<CatalogTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(CatalogTask::class, 'category_id')->orderBy('sort_order');
    }
}
