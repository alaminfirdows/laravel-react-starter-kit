<?php

namespace App\Domain\Knowledge\Http\Resources;

use App\Domain\Knowledge\Models\Competitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Competitor
 */
class CompetitorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'knowledgeDocumentId' => $this->knowledge_document_id,
            'name' => $this->name,
            'url' => $this->url,
            'pricing' => $this->pricing,
            'icp' => $this->icp,
            'positioning' => $this->positioning,
            'features' => $this->features,
            'integrations' => $this->integrations,
            'strengths' => $this->strengths,
            'complaints' => $this->complaints,
            'lastReviewedAt' => $this->last_reviewed_at?->toDateString(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
