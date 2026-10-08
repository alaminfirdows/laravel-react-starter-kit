<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Task\Models\ActionRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActionRun
 */
class ActionRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'channel' => $this->channel,
            'actorType' => $this->actor_type,
            'clientName' => $this->client_name,
            'outputMd' => $this->output_md,
            'error' => $this->error,
            'startedAt' => $this->started_at->toIso8601String(),
            'finishedAt' => $this->finished_at?->toIso8601String(),
        ];
    }
}
