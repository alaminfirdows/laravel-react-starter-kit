<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Task\Models\Evidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Evidence
 */
class EvidenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'label' => $this->label,
            'value' => $this->value,
            'criterionKey' => $this->criterion_key,
            'passed' => $this->passed,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
