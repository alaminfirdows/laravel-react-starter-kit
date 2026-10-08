<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Task\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class AssignedTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'statusLabel' => $this->status->label(),
            'progressPct' => $this->progress_pct,
            'project' => ['name' => $this->project->name, 'slug' => $this->project->slug],
        ];
    }
}
