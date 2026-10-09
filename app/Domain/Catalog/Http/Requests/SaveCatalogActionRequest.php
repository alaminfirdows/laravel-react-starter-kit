<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Data\CatalogActionData;
use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Rules\ActionSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCatalogActionRequest extends FormRequest
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
        $task = $this->route('catalogTask');
        $action = $this->route('catalogAction');

        return [
            'key' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/',
                Rule::unique(CatalogAction::class, 'key')
                    ->where('catalog_task_id', $task instanceof CatalogTask ? $task->id : null)
                    ->ignore($action instanceof CatalogAction ? $action->id : null),
            ],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ActionType::class)],
            'executor' => ['required', Rule::enum(Executor::class)],
            'instructions_md' => ['nullable', 'string', 'max:50000'],
            'prompt' => ['nullable', 'string', Rule::exists(PromptTemplate::class, 'key')],
            'config' => ['nullable', 'json', new ActionSchedule],
            'is_required' => ['boolean'],
            'requires_approval' => ['boolean'],
        ];
    }

    public function toData(): CatalogActionData
    {
        $config = $this->filled('config') ? json_decode($this->string('config')->toString(), true) : null;

        return new CatalogActionData(
            key: $this->string('key')->toString(),
            title: $this->string('title')->toString(),
            type: $this->enum('type', ActionType::class) ?? ActionType::Manual,
            executor: $this->enum('executor', Executor::class) ?? Executor::User,
            instructionsMd: $this->input('instructions_md'),
            promptTemplateId: $this->filled('prompt') ? PromptTemplate::query()->where('key', $this->string('prompt')->toString())->value('id') : null,
            config: is_array($config) && $config !== [] ? $config : null,
            isRequired: $this->boolean('is_required'),
            requiresApproval: $this->boolean('requires_approval'),
        );
    }
}
