<?php

namespace App\Domain\Task\Models;

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\ApprovalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $project_id
 * @property string $subject_type
 * @property string $subject_id
 * @property ActorType $requested_by_type
 * @property string|null $requested_by_id
 * @property string|null $requested_by_client
 * @property string $summary_md
 * @property array<string, mixed>|null $payload
 * @property ApprovalStatus $status
 * @property ActorType|null $decided_by_type
 * @property string|null $decided_by_id
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property CarbonImmutable $created_at
 * @property-read TaskAction|Task $subject
 * @property-read Project $project
 */
#[Fillable(['project_id', 'subject_type', 'subject_id', 'requested_by_type', 'requested_by_id', 'requested_by_client', 'summary_md', 'payload', 'status', 'decided_by_type', 'decided_by_id', 'decided_at', 'decision_note'])]
#[UseFactory(ApprovalFactory::class)]
class Approval extends Model
{
    /** @use HasFactory<ApprovalFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ApprovalStatus::class,
            'requested_by_type' => ActorType::class,
            'decided_by_type' => ActorType::class,
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
