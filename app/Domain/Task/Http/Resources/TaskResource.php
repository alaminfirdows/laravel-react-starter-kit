<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Task page detail. Load `parent`, `children` (withCount children) and `actions` first.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'bodyMd' => $this->body_md,
            'status' => $this->status,
            'statusLabel' => $this->status->label(),
            'priority' => $this->priority,
            'verification' => $this->verification,
            'progressPct' => $this->progress_pct,
            'depth' => $this->depth,
            'isLeaf' => $this->whenLoaded('children', fn (): bool => $this->children->isEmpty()),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'ancestors' => $this->ancestors(),
            'parent' => TaskSummaryResource::make($this->whenLoaded('parent')),
            'children' => TaskSummaryResource::collection($this->whenLoaded('children')),
            'actions' => TaskActionResource::collection($this->whenLoaded('actions')),
            'hasActiveRun' => $this->whenLoaded('actions', fn (): bool => $this->actions->contains(fn (TaskAction $action): bool => $action->status === ActionStatus::Running)),
        ];
    }

    /**
     * Breadcrumb from the root down to the direct parent.
     *
     * @return list<array{id: string, title: string}>
     */
    private function ancestors(): array
    {
        $ancestors = [];

        for ($node = $this->resource->parent; $node !== null; $node = $node->parent) {
            array_unshift($ancestors, ['id' => $node->id, 'title' => $node->title]);
        }

        return $ancestors;
    }
}
