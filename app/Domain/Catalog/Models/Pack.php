<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Project\Enums\ProjectPhase;
use Database\Factories\PackFactory;
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
 * @property array<string, mixed>|null $audience
 * @property bool $is_default
 * @property int $version
 * @property string|null $content_hash
 * @property CatalogStatus $status
 */
#[Fillable(['key', 'name', 'description_md', 'audience', 'is_default', 'version', 'content_hash', 'status'])]
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
        ];
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

    /** @return HasMany<PackItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PackItem::class)->orderBy('sort_order');
    }
}
