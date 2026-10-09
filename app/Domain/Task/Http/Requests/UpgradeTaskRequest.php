<?php

namespace App\Domain\Task\Http\Requests;

use App\Domain\Catalog\Actions\DiffCatalogVersion;
use App\Domain\Task\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpgradeTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && $this->user()->can('update', $task);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fields' => ['array'],
            'fields.*' => ['string', Rule::in(array_keys(DiffCatalogVersion::FIELDS))],
        ];
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return array_values(array_filter((array) $this->validated('fields', []), is_string(...)));
    }
}
