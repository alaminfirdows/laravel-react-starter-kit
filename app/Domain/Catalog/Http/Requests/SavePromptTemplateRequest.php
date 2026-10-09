<?php

namespace App\Domain\Catalog\Http\Requests;

use App\Domain\Catalog\Data\PromptTemplateData;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Prompt\Enums\DeepLinkTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePromptTemplateRequest extends FormRequest
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
        return [
            'key' => [Rule::requiredIf($this->prompt() === null), 'string', 'max:120', 'regex:/^[a-z0-9]+(?:[-.][a-z0-9]+)*$/', Rule::unique(PromptTemplate::class, 'key')],
            'title' => ['required', 'string', 'max:255'],
            'target' => ['required', Rule::enum(DeepLinkTarget::class)],
            'launcher_md' => ['nullable', 'string', 'max:4000'],
            'full_md' => ['required', 'string', 'max:50000'],
            'skill_keys' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function prompt(): ?PromptTemplate
    {
        $prompt = $this->route('promptTemplate');

        return $prompt instanceof PromptTemplate ? $prompt : null;
    }

    public function toData(): PromptTemplateData
    {
        $skills = array_values(array_filter(array_map('trim', explode(',', $this->string('skill_keys')->toString()))));

        return new PromptTemplateData(
            key: $this->prompt()->key ?? $this->string('key')->toString(),
            title: $this->string('title')->toString(),
            fullMd: $this->string('full_md')->toString(),
            launcherMd: $this->input('launcher_md'),
            target: $this->string('target')->toString(),
            skillKeys: $skills ?: null,
            variables: $this->prompt()?->variables === null ? null : array_values($this->prompt()->variables),
        );
    }
}
