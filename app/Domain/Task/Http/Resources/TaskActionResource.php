<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Prompt\Actions\RenderFullPrompt;
use App\Domain\Task\Models\TaskAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'status' => $this->status,
            'instructionsMd' => $this->instructions_md,
            'isRequired' => $this->is_required,
            'prompt' => app(RenderFullPrompt::class)->handle($this->resource),
        ];
    }
}
