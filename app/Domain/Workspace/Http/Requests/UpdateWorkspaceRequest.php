<?php

namespace App\Domain\Workspace\Http\Requests;

use App\Domain\Workspace\Http\Requests\Concerns\InteractsWithWorkspaceRoute;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Rules\WorkspaceSlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceRequest extends FormRequest
{
    use InteractsWithWorkspaceRoute;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->workspace());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:2',
                'max:64',
                new WorkspaceSlug,
                // Soft-deleted workspaces keep their slug reserved.
                Rule::unique(Workspace::class, 'slug')->ignore($this->workspace()->id),
            ],
        ];
    }
}
