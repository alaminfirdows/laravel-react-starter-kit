<?php

namespace App\Domain\Workspace\Http\Requests;

use App\Domain\Workspace\Http\Requests\Concerns\InteractsWithWorkspaceRoute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransferOwnershipRequest extends FormRequest
{
    use InteractsWithWorkspaceRoute;

    public function authorize(): bool
    {
        return $this->user()->can('transferOwnership', $this->workspace())
            && ! $this->user()->is($this->member())
            && $this->member()->belongsToWorkspace($this->workspace());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }
}
