<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Prompt\Actions\BuildDeepLink;
use App\Domain\Prompt\Actions\RenderFullPrompt;
use App\Domain\Task\Enums\ApprovalStatus;
use App\Domain\Task\Models\Approval;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Load `runs`, `evidence` and `approvals` for the run history panel.
 *
 * @mixin TaskAction
 */
class TaskActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'executor' => $this->executor,
            'executorLabel' => $this->executor->label(),
            'runsInApp' => $this->executor->runsInApp(),
            'status' => $this->status,
            'statusLabel' => $this->status->label(),
            'instructionsMd' => $this->instructions_md,
            'isRequired' => $this->is_required,
            'isRecurring' => $this->isRecurring(),
            'prompt' => app(RenderFullPrompt::class)->handle($this->resource),
            'deepLink' => app(BuildDeepLink::class)->handle($this->resource)->toArray(),
            'runs' => ActionRunResource::collection($this->whenLoaded('runs')),
            'evidence' => EvidenceResource::collection($this->whenLoaded('evidence')),
            'pendingApproval' => $this->whenLoaded('approvals', fn (): ?ApprovalResource => ($approval = $this->approvals->first(fn (Approval $approval): bool => $approval->status === ApprovalStatus::Pending))
                ? ApprovalResource::make($approval)
                : null),
        ];
    }
}
