<?php

namespace App\Mcp\Tools;

use App\Domain\Knowledge\Actions\SaveDocument;
use App\Domain\Knowledge\Data\DocumentData;
use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('save_knowledge')]
#[Description('Create a knowledge document, or update one (pass document_id) as a new version. Use it to persist outputs like an ICP or competitor notes. Saves as draft unless the founder approved it.')]
class SaveKnowledgeTool extends Tool
{
    public const int MAX_BODY = 200_000;

    public function __construct(private SaveDocument $save) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'document_id' => ['nullable', 'string'],
            'doc_type' => ['required_without:document_id', Rule::enum(DocType::class)],
            'title' => ['required', 'string', 'max:255'],
            'body_md' => ['required', 'string', 'max:'.self::MAX_BODY],
            'status' => ['nullable', Rule::in([DocStatus::Draft->value, DocStatus::Approved->value])],
            'task_id' => ['nullable', 'string'],
            'change_note' => ['nullable', 'string', 'max:255'],
        ]);

        $project = $mcp->project($validated['project_id'], 'update');
        $document = isset($validated['document_id']) ? $mcp->document($validated['document_id'], 'update') : null;
        $task = isset($validated['task_id']) ? $mcp->task($validated['task_id']) : null;

        if (($document !== null && $document->project_id !== $project->id) || ($task !== null && $task->project_id !== $project->id)) {
            throw ValidationException::withMessages(['project_id' => 'Document and task must belong to this project.']);
        }

        $saved = $this->save->handle($project, new DocumentData(
            docType: $document->doc_type ?? DocType::from($validated['doc_type']),
            title: $validated['title'],
            bodyMd: $validated['body_md'],
            status: DocStatus::from($validated['status'] ?? DocStatus::Draft->value),
            source: DocSource::ClaudeMcp,
            taskId: $task?->id,
            changeNote: $validated['change_note'] ?? null,
        ), $mcp->actor(), $document);

        return Response::text("Saved document `{$saved->id}` ({$saved->doc_type->value}, {$saved->status->value}, v{$saved->version}).");
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (ULID).')->required(),
            'document_id' => $schema->string()->description('Existing document to update. Omit to create a new one.'),
            'doc_type' => $schema->string()->enum(DocType::class)->description('Document type. Required for new documents.'),
            'title' => $schema->string()->description('Document title.')->required(),
            'body_md' => $schema->string()->description('Full document body in Markdown.')->required(),
            'status' => $schema->string()->enum([DocStatus::Draft->value, DocStatus::Approved->value])->description('draft (default) or approved — only when the founder approved this content.'),
            'task_id' => $schema->string()->description('Task this document came from.'),
            'change_note' => $schema->string()->description('Short note on what changed.'),
        ];
    }
}
