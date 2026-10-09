<?php

namespace App\Domain\Task\Http\Resources;

use App\Domain\Catalog\Data\CatalogFieldDiff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatalogFieldDiff
 */
class CatalogFieldDiffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'field' => $this->field,
            'label' => $this->label,
            'founder' => $this->text($this->founder),
            'catalog' => $this->text($this->catalog),
            'isConflict' => $this->isConflict,
        ];
    }

    private function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_array($value) => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
