<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Data\PackData;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'key' => [Rule::requiredIf($this->pack() === null), 'string', 'max:120', 'regex:/^[a-z0-9]+(?:[-.][a-z0-9]+)*$/', Rule::unique(Pack::class, 'key')],
            'name' => ['required', 'string', 'max:255'],
            'description_md' => ['nullable', 'string', 'max:10000'],
            'phase' => ['required', Rule::enum(ProjectPhase::class)],
            'status' => ['required', Rule::enum(CatalogStatus::class)],
            'is_default' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', 'distinct', Rule::exists(CatalogTask::class, 'key')],
        ];
    }

    public function pack(): ?Pack
    {
        $pack = $this->route('pack');

        return $pack instanceof Pack ? $pack : null;
    }

    public function toData(): PackData
    {
        $keys = array_values(array_map(strval(...), $this->array('items')));
        $ids = CatalogTask::query()->whereIn('key', $keys)->pluck('id', 'key');

        return new PackData(
            key: $this->pack()->key ?? $this->string('key')->toString(),
            name: $this->string('name')->toString(),
            phase: $this->enum('phase', ProjectPhase::class) ?? ProjectPhase::Planning,
            items: array_map(fn (string $key): array => ['catalog_task_id' => (int) $ids[$key], 'include_subtree' => true], $keys),
            descriptionMd: $this->input('description_md'),
            isDefault: $this->boolean('is_default'),
            status: $this->enum('status', CatalogStatus::class) ?? CatalogStatus::Draft,
        );
    }
}
