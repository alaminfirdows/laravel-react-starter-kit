<?php

namespace App\Domain\Catalog\Http\Resources;

use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PackItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pack
 */
class CommunityPackResource extends JsonResource
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
            'visibility' => $this->visibility->value,
            'status' => $this->status->value,
            'reviewStatus' => $this->review_status?->value,
            'reviewNote' => $this->review_note,
            'version' => $this->version,
            'owner' => $this->whenLoaded('ownerWorkspace', fn (): ?string => $this->ownerWorkspace?->name),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PackItem $item): array => [
                'key' => $item->catalogTask->key,
                'title' => $item->catalogTask->title,
            ])->all()),
            'itemsCount' => $this->whenCounted('items'),
        ];
    }
}
