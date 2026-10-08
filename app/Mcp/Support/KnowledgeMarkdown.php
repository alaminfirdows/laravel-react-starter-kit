<?php

namespace App\Mcp\Support;

use App\Domain\Knowledge\Data\SearchResultData;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Compact Markdown for knowledge tool output.
 */
class KnowledgeMarkdown
{
    public const int SNIPPET_CHARS = 600;

    /**
     * @param  list<SearchResultData>  $results
     */
    public function results(array $results): string
    {
        if ($results === []) {
            return 'No matching knowledge found.';
        }

        return collect($results)->map(function (SearchResultData $result, int $index): string {
            $where = $result->headingPath ? " › {$result->headingPath}" : '';

            return ($index + 1).". **{$result->documentTitle}** · {$result->docType->value} · `{$result->documentId}`{$where}\n"
                .Str::limit(trim($result->content), self::SNIPPET_CHARS);
        })->implode("\n\n");
    }

    /**
     * @param  Collection<int, KnowledgeDocument>  $documents
     */
    public function list(Collection $documents): string
    {
        if ($documents->isEmpty()) {
            return 'No documents yet.';
        }

        return $documents
            ->map(fn (KnowledgeDocument $document): string => "- `{$document->id}` · {$document->doc_type->value} · {$document->status->value} · v{$document->version} · {$document->title}")
            ->implode("\n");
    }

    public function document(KnowledgeDocument $document): string
    {
        return "# {$document->title}\n"
            ."Document `{$document->id}` · {$document->doc_type->value} · {$document->status->value} · v{$document->version}"
            ." · updated {$document->updated_at?->toDateString()}\n\n"
            .$document->body_md;
    }
}
