<?php

namespace App\Domain\Project\Http\Resources;

use App\Domain\Catalog\Models\Pack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pack
 */
class AvailablePackResource extends JsonResource
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
            'itemsCount' => $this->whenCounted('items'),
        ];
    }
}
