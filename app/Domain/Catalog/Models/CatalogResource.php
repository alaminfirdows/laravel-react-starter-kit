<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ResourceType;
use Database\Factories\CatalogResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property ResourceType $type
 * @property string $title
 * @property string|null $url
 * @property string|null $description_md
 * @property bool $is_affiliate
 * @property array<int, string>|null $region
 * @property array<string, mixed>|null $meta
 */
#[Table('resources')]
#[Fillable(['key', 'type', 'title', 'url', 'description_md', 'is_affiliate', 'region', 'meta'])]
#[UseFactory(CatalogResourceFactory::class)]
class CatalogResource extends Model
{
    /** @use HasFactory<CatalogResourceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'is_affiliate' => 'boolean',
            'region' => 'array',
            'meta' => 'array',
        ];
    }
}
