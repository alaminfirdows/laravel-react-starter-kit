<?php

namespace App\Domain\Task\Models;

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Media\Models\Media;
use App\Domain\Task\Enums\EvidenceKind;
use Carbon\CarbonImmutable;
use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proof for a completion criterion (DATA_MODEL §F.2).
 *
 * @property string $id
 * @property string $project_id
 * @property string $task_id
 * @property string|null $task_action_id
 * @property string|null $action_run_id
 * @property string|null $criterion_key
 * @property EvidenceKind $kind
 * @property string $label
 * @property string|null $value
 * @property bool|null $passed
 * @property string|null $media_id
 * @property CarbonImmutable|null $verified_at
 * @property ActorType $created_by_type
 * @property string|null $created_by_id
 * @property CarbonImmutable $created_at
 * @property-read Task $task
 * @property-read TaskAction|null $action
 * @property-read Media|null $media
 */
#[Table('evidence')]
#[Fillable(['project_id', 'task_id', 'task_action_id', 'action_run_id', 'criterion_key', 'kind', 'label', 'value', 'passed', 'media_id', 'verified_at', 'verified_by_type', 'verified_by_id', 'created_by_type', 'created_by_id'])]
#[UseFactory(EvidenceFactory::class)]
class Evidence extends Model
{
    /** @use HasFactory<EvidenceFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'kind' => EvidenceKind::class,
            'passed' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'created_by_type' => ActorType::class,
        ];
    }

    /**
     * Counts toward a criterion: a check result only when it passed.
     */
    public function satisfies(): bool
    {
        return $this->kind !== EvidenceKind::CheckResult || $this->passed === true;
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<TaskAction, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(TaskAction::class, 'task_action_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
