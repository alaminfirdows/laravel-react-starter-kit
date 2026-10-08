<?php

namespace App\Domain\Workspace\Http\Requests;

use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Http\Requests\Concerns\InteractsWithWorkspaceRoute;
use App\Domain\Workspace\Rules\UniqueWorkspaceInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    use InteractsWithWorkspaceRoute;

    public function authorize(): bool
    {
        return $this->user()->can('inviteMember', $this->workspace());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignable = $this->user()->workspaceRole($this->workspace())?->assignableRoles() ?? [];

        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueWorkspaceInvitation($this->workspace())],
            'role' => ['required', 'string', Rule::enum(WorkspaceRole::class)->only($assignable)],
        ];
    }

    public function role(): WorkspaceRole
    {
        return WorkspaceRole::from($this->validated('role'));
    }
}
