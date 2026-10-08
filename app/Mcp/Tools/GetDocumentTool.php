<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\KnowledgeMarkdown;
use App\Mcp\Support\McpActor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_document')]
#[Description('Full Markdown of one knowledge document (current version).')]
#[IsReadOnly]
class GetDocumentTool extends Tool
{
    public function __construct(private KnowledgeMarkdown $markdown) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate(['document_id' => ['required', 'string']]);

        return Response::text($this->markdown->document($mcp->document($validated['document_id'])));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->string()->description('Document ID (ULID) from search_knowledge, list_documents or the project context.')->required(),
        ];
    }
}
