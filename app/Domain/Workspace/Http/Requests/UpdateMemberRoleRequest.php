<?php

namespace App\Domain\Workspace\Http\Requests;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Http\Requests\Concerns\InteractsWithWorkspaceRoute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRoleRequest extends FormRequest
{
    use InteractsWithWorkspaceRoute;

    public function authorize(): bool
    {
        return $this->user()->can('updateMember', [$this->workspace(), $this->member()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignable = $this->user()->workspaceRole($this->workspace())?->assignableRoles() ?? [];

        return [
            'role' => ['required', 'string', Rule::enum(WorkspaceRole::class)->only($assignable)],
        ];
    }

    public function role(): WorkspaceRole
    {
        return WorkspaceRole::from($this->validated('role'));
    }
}
