<?php

namespace App\Domain\Knowledge\Http\Resources;

use App\Domain\Knowledge\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Interview
 */
class InterviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'knowledgeDocumentId' => $this->knowledge_document_id,
            'person' => $this->person,
            'company' => $this->company,
            'role' => $this->role,
            'interviewedOn' => $this->interviewed_on?->toDateString(),
            'problem' => $this->problem,
            'currentSolution' => $this->current_solution,
            'pain' => $this->pain,
            'desiredOutcome' => $this->desired_outcome,
            'objections' => $this->objections,
            'quotes' => $this->quotes,
            'featureRequests' => $this->feature_requests,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
