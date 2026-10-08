<?php

namespace App\Mcp\Tools;

use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Mcp\Support\KnowledgeMarkdown;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_documents')]
#[Description('List knowledge documents of a project with type, status and version. Archived documents are hidden unless asked for.')]
#[IsReadOnly]
class ListDocumentsTool extends Tool
{
    public const int LIMIT = 100;

    public function __construct(private KnowledgeMarkdown $markdown) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'doc_type' => ['nullable', Rule::enum(DocType::class)],
            'status' => ['nullable', Rule::enum(DocStatus::class)],
        ]);

        $documents = $mcp->project($validated['project_id'])->knowledgeDocuments()
            ->when($validated['doc_type'] ?? null, fn ($documents, string $type) => $documents->where('doc_type', $type))
            ->when(
                $validated['status'] ?? null,
                fn ($documents, string $status) => $documents->where('status', $status),
                fn ($documents) => $documents->where('status', '!=', DocStatus::Archived),
            )
            ->orderBy('doc_type')
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get(['id', 'doc_type', 'status', 'version', 'title']);

        return Response::text($this->markdown->list($documents));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (ULID).')->required(),
            'doc_type' => $schema->string()->enum(DocType::class)->description('Only this document type.'),
            'status' => $schema->string()->enum(DocStatus::class)->description('Only this status. Default: draft and approved.'),
        ];
    }
}
