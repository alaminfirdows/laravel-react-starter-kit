<?php

namespace App\Domain\Catalog\Http\Resources;

use App\Domain\Catalog\Models\CatalogTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatalogTask
 */
class AdminCatalogTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'summary' => $this->summary,
            'bodyMd' => $this->body_md,
            'categoryId' => $this->category_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? ['key' => $this->parent->key, 'title' => $this->parent->title] : null),
            'priority' => $this->priority_default->value,
            'estMinutes' => $this->est_minutes,
            'difficulty' => $this->difficulty,
            'isOptional' => $this->is_optional,
            'status' => $this->status->value,
            'version' => $this->version,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'adminEditedAt' => $this->admin_edited_at?->toIso8601String(),
        ];
    }
}
