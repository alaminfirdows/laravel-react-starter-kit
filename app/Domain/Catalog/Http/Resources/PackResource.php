<?php

namespace App\Domain\Catalog\Http\Resources;

use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PackItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pack
 */
class PackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'descriptionMd' => $this->description_md,
            'phase' => $this->audience['phase'] ?? null,
            'isDefault' => $this->is_default,
            'status' => $this->status->value,
            'version' => $this->version,
            'adminEditedAt' => $this->admin_edited_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PackItem $item): string => $item->catalogTask->key)->all()),
            'itemsCount' => $this->whenCounted('items'),
        ];
    }
}
