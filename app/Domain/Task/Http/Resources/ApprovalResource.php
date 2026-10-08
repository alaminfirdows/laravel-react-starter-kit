<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\TaskAction;
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
            'decidedAt' => $this->decided_at?->toIso8601String(),
            'subject' => $this->whenLoaded('subject', fn (): array => $this->subject instanceof TaskAction ? [
                'title' => $this->subject->title,
                'taskId' => $this->subject->task_id,
                'taskTitle' => $this->subject->task->title,
            ] : [
                'title' => $this->subject->title,
                'taskId' => $this->subject->id,
                'taskTitle' => $this->subject->title,
            ]),
        ];
    }
}
