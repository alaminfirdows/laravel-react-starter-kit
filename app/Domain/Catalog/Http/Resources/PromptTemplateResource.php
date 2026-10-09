<?php

namespace App\Domain\Catalog\Http\Resources;

use App\Domain\Catalog\Models\PromptTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromptTemplate
 */
class PromptTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'target' => $this->target,
            'launcherMd' => $this->launcher_md,
            'fullMd' => $this->full_md,
            'skillKeys' => implode(', ', $this->skill_keys ?? []),
            'version' => $this->version,
            'adminEditedAt' => $this->admin_edited_at?->toIso8601String(),
        ];
    }
}
