<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Knowledge\Policies\InterviewPolicy;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer interview row (DATA_MODEL §D), mirrored to an `interview` knowledge document.
 *
 * @property string $id
 * @property string $project_id
 * @property string|null $knowledge_document_id
 * @property string $person
 * @property string|null $company
 * @property string|null $role
 * @property CarbonImmutable|null $interviewed_on
 * @property string|null $problem
 * @property string|null $current_solution
 * @property string|null $pain
 * @property string|null $desired_outcome
 * @property string|null $objections
 * @property string|null $quotes
 * @property string|null $feature_requests
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read KnowledgeDocument|null $knowledgeDocument
 */
#[Fillable(['project_id', 'workspace_id', 'knowledge_document_id', 'person', 'company', 'role', 'interviewed_on', 'problem', 'current_solution', 'pain', 'desired_outcome', 'objections', 'quotes', 'feature_requests'])]
#[UseFactory(InterviewFactory::class)]
#[UsePolicy(InterviewPolicy::class)]
class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use BelongsToWorkspace, HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'interviewed_on' => 'immutable_date',
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
