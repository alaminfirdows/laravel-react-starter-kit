<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\PromptTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string|null $launcher_md
 * @property string $full_md
 * @property array<int, string>|null $variables
 * @property string $target
 * @property array<int, string>|null $skill_keys
 * @property int $version
 * @property string $content_hash
 */
#[Fillable(['key', 'title', 'launcher_md', 'full_md', 'variables', 'target', 'skill_keys', 'version', 'content_hash'])]
#[UseFactory(PromptTemplateFactory::class)]
class PromptTemplate extends Model
{
    /** @use HasFactory<PromptTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'skill_keys' => 'array',
        ];
    }
}
