<?php

namespace App\Domain\Task\Http\Requests;

use App\Domain\Task\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
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
            'assignee_id' => ['nullable', 'ulid', 'exists:users,id'],
        ];
    }

    public function assignee(): ?User
    {
        return $this->filled('assignee_id')
            ? User::query()->findOrFail($this->string('assignee_id')->value())
            : null;
    }
}
