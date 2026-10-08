<?php

namespace App\Domain\Knowledge\Http\Resources;

use App\Domain\Knowledge\Data\SearchResultData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property SearchResultData $resource
 */
class SearchResultResource extends JsonResource
{
    public const int SNIPPET_CHARS = 280;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'chunkId' => $this->resource->chunkId,
            'documentId' => $this->resource->documentId,
            'documentTitle' => $this->resource->documentTitle,
            'docTypeLabel' => $this->resource->docType->label(),
            'headingPath' => $this->resource->headingPath,
            'snippet' => Str::limit(trim($this->resource->content), self::SNIPPET_CHARS),
        ];
    }
}
