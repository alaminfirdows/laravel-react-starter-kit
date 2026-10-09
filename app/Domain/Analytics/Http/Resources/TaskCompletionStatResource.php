<?php

namespace App\Domain\Analytics\Http\Resources;

use App\Domain\Analytics\Data\TaskCompletionStat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskCompletionStat
 */
class TaskCompletionStatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->catalogTaskKey,
            'title' => $this->title,
            'projects' => $this->projects,
            'started' => $this->started,
            'completed' => $this->completed,
            'completionRate' => $this->completionRate(),
            'avgHoursToComplete' => $this->avgHoursToComplete,
        ];
    }
}
