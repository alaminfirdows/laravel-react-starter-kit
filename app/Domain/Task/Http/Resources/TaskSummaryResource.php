<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Task\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Parent card and subtask rows on the task page.
 *
 * @mixin Task
 */
class TaskSummaryResource extends JsonResource
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
            'status' => $this->status,
            'progressPct' => $this->progress_pct,
            'isLeaf' => $this->whenCounted('children', fn (int $count): bool => $count === 0),
        ];
    }
}
