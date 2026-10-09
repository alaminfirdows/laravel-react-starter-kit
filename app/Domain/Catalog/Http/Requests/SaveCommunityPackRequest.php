<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Data\CommunityPackData;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Enums\PackVisibility;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommunityPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pack = $this->route('pack');

        return $pack instanceof Pack
            ? $this->user()->can('update', $pack)
            : $this->user()->can('create', Pack::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description_md' => ['nullable', 'string', 'max:2000'],
            'phase' => ['required', Rule::enum(ProjectPhase::class)],
            'visibility' => ['required', Rule::enum(PackVisibility::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', 'distinct', Rule::exists(CatalogTask::class, 'key')->where(fn (Builder $query) => $query
                ->whereNull('parent_id')
                ->where('status', CatalogStatus::Published->value))],
        ];
    }

    public function toData(): CommunityPackData
    {
        $keys = array_values(array_map(strval(...), $this->array('items')));
        $ids = CatalogTask::query()->whereIn('key', $keys)->pluck('id', 'key');

        return new CommunityPackData(
            name: $this->string('name')->trim()->toString(),
            phase: $this->enum('phase', ProjectPhase::class) ?? ProjectPhase::Planning,
            visibility: $this->enum('visibility', PackVisibility::class) ?? PackVisibility::Private,
            catalogTaskIds: array_map(fn (string $key): int => (int) $ids[$key], $keys),
            descriptionMd: $this->input('description_md'),
        );
    }
}
