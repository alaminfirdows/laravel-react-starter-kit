<?php

namespace App\Domain\Workspace\Http\Requests;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Workspace $workspace */
        $workspace = $this->route('workspace');

        return $this->user()->can('update', $workspace);
    }

    /**
     * SVG is not allowed (can carry scripts).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ];
    }
}
