<?php

namespace App\Domain\Knowledge\Http\Resources;

use App\Domain\Knowledge\Models\Decision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Decision
 */
class DecisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'decisionMd' => $this->decision_md,
            'rationaleMd' => $this->rationale_md,
            'alternatives' => $this->alternatives ?? [],
            'decidedOn' => $this->decided_on->toDateString(),
            'revisitOn' => $this->revisit_on?->toDateString(),
            'source' => $this->source,
            'owner' => $this->whenLoaded('owner', fn (): ?string => $this->owner?->name),
        ];
    }
}
