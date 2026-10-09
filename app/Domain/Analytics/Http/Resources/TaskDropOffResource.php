<?php

namespace App\Domain\Analytics\Http\Resources;

use App\Domain\Analytics\Data\TaskDropOff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskDropOff
 */
class TaskDropOffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->catalogTaskKey,
            'title' => $this->title,
            'started' => $this->started,
            'stalled' => $this->stalled,
            'dropOffRate' => $this->dropOffRate(),
        ];
    }
}
