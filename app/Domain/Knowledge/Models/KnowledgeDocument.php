<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Policies\KnowledgeDocumentPolicy;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Markdown document in the project brain (DATA_MODEL §D). `body_md` is the current version.
 *
 * @property string $id
 * @property string $project_id
 * @property string|null $task_id
 * @property DocType $doc_type
 * @property string $title
 * @property string $body_md
 * @property DocStatus $status
 * @property DocSource $source
 * @property int $version
 * @property string $checksum
 * @property CarbonImmutable|null $embedded_at
 * @property list<string>|null $tags
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read Task|null $task
 */
#[Fillable(['project_id', 'workspace_id', 'task_id', 'doc_type', 'title', 'body_md', 'status', 'source', 'version', 'checksum', 'embedded_at', 'tags', 'meta'])]
#[UseFactory(KnowledgeDocumentFactory::class)]
#[UsePolicy(KnowledgeDocumentPolicy::class)]
class KnowledgeDocument extends Model
{
    /** @use HasFactory<KnowledgeDocumentFactory> */
    use BelongsToWorkspace, HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'doc_type' => DocType::class,
            'status' => DocStatus::class,
            'source' => DocSource::class,
            'embedded_at' => 'immutable_datetime',
            'tags' => 'array',
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public static function checksumFor(string $bodyMd): string
    {
        return hash('sha256', $bodyMd);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return HasMany<KnowledgeDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeDocumentVersion::class, 'document_id');
    }

    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id');
    }
}
