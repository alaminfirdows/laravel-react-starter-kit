<?php

namespace App\Domain\Knowledge\Http\Resources;

use App\Domain\Knowledge\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KnowledgeDocument
 */
class KnowledgeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'docType' => $this->doc_type,
            'docTypeLabel' => $this->doc_type->label(),
            'title' => $this->title,
            'status' => $this->status,
            'source' => $this->source,
            'version' => $this->version,
            'isEmbedded' => $this->embedded_at !== null,
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'bodyMd' => $this->whenHas('body_md'),
            'versions' => KnowledgeDocumentVersionResource::collection($this->whenLoaded('versions')),
        ];
    }
}
