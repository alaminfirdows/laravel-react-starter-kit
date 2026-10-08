<?php

namespace App\Domain\Activity\Http\Resources;

use App\Domain\Activity\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'actorType' => $this->actor_type,
            'actorName' => $this->whenLoaded('actor', fn (): ?string => $this->actor?->name),
            'clientName' => $this->client_name,
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? ['name' => $this->project->name, 'slug' => $this->project->slug] : null),
            'channel' => $this->channel,
            'properties' => $this->properties ?? [],
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
