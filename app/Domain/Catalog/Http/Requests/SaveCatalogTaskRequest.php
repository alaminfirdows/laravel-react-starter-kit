<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Data\CatalogTaskData;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Task\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCatalogTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->task() === null;

        return [
            'key' => [Rule::requiredIf($creating), 'string', 'max:120', 'regex:/^[a-z0-9]+(?:[-.][a-z0-9]+)*$/', Rule::unique(CatalogTask::class, 'key')->ignore($this->task())],
            'parent' => ['nullable', 'string', Rule::exists(CatalogTask::class, 'key')],
            'category_id' => [Rule::requiredIf($creating && ! $this->filled('parent')), 'nullable', 'integer', Rule::exists(CatalogCategory::class, 'id')],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:280'],
            'body_md' => ['nullable', 'string', 'max:100000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'est_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_optional' => ['boolean'],
        ];
    }

    public function task(): ?CatalogTask
    {
        $task = $this->route('catalogTask');

        return $task instanceof CatalogTask ? $task : null;
    }

    public function toData(): CatalogTaskData
    {
        $task = $this->task();
        $parent = $this->filled('parent') ? CatalogTask::query()->where('key', $this->string('parent')->toString())->firstOrFail() : null;

        return new CatalogTaskData(
            key: $task->key ?? $this->string('key')->toString(),
            categoryId: $this->integer('category_id') ?: ($parent->category_id ?? $task->category_id ?? 0),
            title: $this->string('title')->toString(),
            parentId: $parent?->id,
            summary: $this->input('summary'),
            bodyMd: $this->input('body_md'),
            priority: $this->enum('priority', TaskPriority::class) ?? TaskPriority::P2,
            estMinutes: $this->filled('est_minutes') ? $this->integer('est_minutes') : null,
            difficulty: $this->filled('difficulty') ? $this->integer('difficulty') : null,
            isOptional: $this->boolean('is_optional'),
            completionCriteria: $task?->completion_criteria === null ? null : array_values($task->completion_criteria),
            expectedOutputs: $task?->expected_outputs === null ? null : array_values($task->expected_outputs),
        );
    }
}
