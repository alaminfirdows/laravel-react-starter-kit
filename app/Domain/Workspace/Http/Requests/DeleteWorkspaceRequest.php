<?php

namespace App\Domain\Workspace\Http\Requests;

use App\Domain\Workspace\Http\Requests\Concerns\InteractsWithWorkspaceRoute;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteWorkspaceRequest extends FormRequest
{
    use InteractsWithWorkspaceRoute;

    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->workspace());
    }

    /**
     * User must type the workspace name to confirm.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::in([$this->workspace()->name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.in' => __('The name does not match the workspace name.'),
        ];
    }
}
