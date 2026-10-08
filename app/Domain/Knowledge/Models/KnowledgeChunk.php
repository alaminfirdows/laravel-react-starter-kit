<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A searchable slice of a document: embedding for semantic search, generated `tsv` for full text.
 *
 * @property string $id
 * @property string $project_id
 * @property string $document_id
 * @property int $chunk_index
 * @property string|null $heading_path
 * @property string $content
 * @property int $token_count
 * @property list<float>|null $embedding
 * @property array<string, mixed>|null $meta
 * @property-read KnowledgeDocument $document
 */
#[Fillable(['workspace_id', 'project_id', 'document_id', 'chunk_index', 'heading_path', 'content', 'token_count', 'embedding', 'meta'])]
class KnowledgeChunk extends Model
{
    use BelongsToWorkspace, HasUlids;

    public const int DIMENSIONS = 1536;

    protected function casts(): array
    {
        return [
            'embedding' => AsVector::class,
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }
}
