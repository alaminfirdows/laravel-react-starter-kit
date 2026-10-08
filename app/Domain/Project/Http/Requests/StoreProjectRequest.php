<?php

namespace App\Domain\Project\Http\Requests;

use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phase' => ['required', Rule::enum(ProjectPhase::class)],
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
