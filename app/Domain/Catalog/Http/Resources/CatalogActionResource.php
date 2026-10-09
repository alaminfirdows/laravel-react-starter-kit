<?php

namespace App\Domain\Catalog\Http\Resources;

use App\Domain\Catalog\Models\CatalogAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatalogAction
 */
class CatalogActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'type' => $this->type->value,
            'executor' => $this->executor->value,
            'instructionsMd' => $this->instructions_md,
            'prompt' => $this->promptTemplate?->key,
            'config' => $this->config === null ? null : json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'isRequired' => $this->is_required,
            'requiresApproval' => $this->requires_approval,
            'version' => $this->version,
        ];
    }
}
