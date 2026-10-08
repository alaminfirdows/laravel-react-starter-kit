<?php

namespace App\Domain\Knowledge\Http\Requests;

use App\Domain\Knowledge\Data\DocumentData;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKnowledgeDocumentRequest extends FormRequest
{
    public const int MAX_BODY = 200_000;

    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('update', $project);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'doc_type' => [Rule::requiredIf($this->document() === null), Rule::enum(DocType::class)],
            'title' => ['required', 'string', 'max:255'],
            'body_md' => ['required', 'string', 'max:'.self::MAX_BODY],
            'status' => ['required', Rule::enum(DocStatus::class)],
            'change_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function document(): ?KnowledgeDocument
    {
        $document = $this->route('knowledgeDocument');

        return $document instanceof KnowledgeDocument ? $document : null;
    }

    public function toData(): DocumentData
    {
        return new DocumentData(
            docType: $this->document()->doc_type ?? $this->enum('doc_type', DocType::class) ?? DocType::Other,
            title: $this->string('title')->toString(),
            bodyMd: $this->string('body_md')->toString(),
            status: $this->enum('status', DocStatus::class) ?? DocStatus::Draft,
            changeNote: $this->input('change_note'),
        );
    }
}
