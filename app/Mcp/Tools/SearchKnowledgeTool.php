<?php

namespace App\Mcp\Tools;

use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Queries\HybridSearch;
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

#[Name('search_knowledge')]
#[Description('Search the project knowledge base (ICP, positioning, interviews, research, …) by meaning and keywords. Returns ranked snippets with document IDs; call get_document for the full text.')]
#[IsReadOnly]
class SearchKnowledgeTool extends Tool
{
    public const int MAX_LIMIT = 20;

    public function __construct(private HybridSearch $search, private KnowledgeMarkdown $markdown) {}

    public function handle(Request $request): Response
    {
        $mcp = McpActor::from($request);
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'query' => ['required', 'string', 'max:500'],
            'doc_types' => ['nullable', 'array'],
            'doc_types.*' => [Rule::enum(DocType::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        $results = $this->search->handle(
            $mcp->project($validated['project_id']),
            $validated['query'],
            $validated['limit'] ?? 8,
            array_values(array_map(DocType::from(...), $validated['doc_types'] ?? [])),
        );

        return Response::text($this->markdown->results($results));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (ULID).')->required(),
            'query' => $schema->string()->description('What to look for, in plain words.')->required(),
            'doc_types' => $schema->array()->items($schema->string()->enum(DocType::class))->description('Only these document types.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->description('Max results. Default 8.'),
        ];
    }
}
