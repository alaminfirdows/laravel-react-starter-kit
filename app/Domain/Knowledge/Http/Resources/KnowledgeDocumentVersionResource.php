<?php

namespace App\Domain\Knowledge\Http\Resources;

use App\Domain\Knowledge\Models\KnowledgeDocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KnowledgeDocumentVersion
 */
class KnowledgeDocumentVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'changeNote' => $this->change_note,
            'createdByType' => $this->created_by_type,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
