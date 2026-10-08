<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Activity\Enums\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot of a document body. Scoped through its document.
 *
 * @property string $id
 * @property string $document_id
 * @property int $version
 * @property string $body_md
 * @property string $checksum
 * @property ActorType $created_by_type
 * @property string|null $created_by_id
 * @property string|null $change_note
 * @property CarbonImmutable $created_at
 * @property-read KnowledgeDocument $document
 */
#[Fillable(['document_id', 'version', 'body_md', 'checksum', 'created_by_type', 'created_by_id', 'change_note'])]
class KnowledgeDocumentVersion extends Model
{
    use HasUlids;

    public const null UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_by_type' => ActorType::class,
            'created_at' => 'immutable_datetime',
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
