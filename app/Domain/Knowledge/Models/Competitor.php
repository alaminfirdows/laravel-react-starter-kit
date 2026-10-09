<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Knowledge\Policies\CompetitorPolicy;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Competitor row (DATA_MODEL §D), mirrored to a `competitor` knowledge document.
 *
 * @property string $id
 * @property string $project_id
 * @property string|null $knowledge_document_id
 * @property string $name
 * @property string|null $url
 * @property string|null $pricing
 * @property string|null $icp
 * @property string|null $positioning
 * @property string|null $features
 * @property string|null $integrations
 * @property string|null $strengths
 * @property string|null $complaints
 * @property CarbonImmutable|null $last_reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read KnowledgeDocument|null $knowledgeDocument
 */
#[Fillable(['project_id', 'workspace_id', 'knowledge_document_id', 'name', 'url', 'pricing', 'icp', 'positioning', 'features', 'integrations', 'strengths', 'complaints', 'last_reviewed_at'])]
#[UseFactory(CompetitorFactory::class)]
#[UsePolicy(CompetitorPolicy::class)]
class Competitor extends Model
{
    /** @use HasFactory<CompetitorFactory> */
    use BelongsToWorkspace, HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'last_reviewed_at' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function knowledgeDocument(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }
}
