<?php

namespace App\Domain\Project\Http\Resources;

use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'oneLiner' => $this->one_liner,
            'phase' => $this->phase,
            'phaseLabel' => $this->phase->label(),
            'status' => $this->status,
            'logoUrl' => $this->logo_url,
            'setupStep' => $this->when(
                $this->isDraft(),
                fn (): string => (ProjectSetupStep::firstIncomplete($this->resource) ?? ProjectSetupStep::Goals)->value,
            ),
        ];
    }
}
