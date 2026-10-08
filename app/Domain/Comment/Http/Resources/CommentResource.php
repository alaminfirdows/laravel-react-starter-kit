<?php

namespace App\Domain\Comment\Http\Resources;

use App\Domain\Comment\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bodyMd' => $this->body_md,
            'authorType' => $this->author_type,
            'authorName' => $this->author?->name,
            'clientName' => $this->client_name,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
