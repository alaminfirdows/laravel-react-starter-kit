<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Task\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Approval
 */
class ApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'summaryMd' => $this->summary_md,
            'requestedByClient' => $this->requested_by_client,
            'decisionNote' => $this->decision_note,
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
