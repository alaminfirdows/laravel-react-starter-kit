<?php

namespace App\Domain\Task\Http\Requests;

use App\Domain\Project\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DecideApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()->can('update', $project);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
