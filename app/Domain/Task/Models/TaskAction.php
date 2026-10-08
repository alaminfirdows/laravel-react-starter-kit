<?php

namespace App\Domain\Task\Models;

use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Task\Enums\ActionStatus;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use Carbon\CarbonImmutable;
use Database\Factories\TaskActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string $id
 * @property string $task_id
 * @property string $project_id
 * @property int|null $catalog_action_id
 * @property ActionType $type
 * @property Executor $executor
 * @property string $title
 * @property string|null $instructions_md
 * @property int|null $prompt_template_id
 * @property string|null $prompt_override_md
 * @property array<string, mixed>|null $config
 * @property bool $is_required
 * @property bool $requires_approval
 * @property ActionStatus $status
 * @property int $sort_order
 * @property string|null $last_run_id
 * @property CarbonImmutable|null $completed_at
 * @property string|null $completed_by_type
 * @property string|null $completed_by_id
 * @property-read Task $task
 * @property-read PromptTemplate|null $promptTemplate
 * @property-read CatalogAction|null $catalogAction
 * @property-read ActionRun|null $lastRun
 * @property-read Collection<int, ActionRun> $runs
 * @property-read Collection<int, Evidence> $evidence
 * @property-read Collection<int, Approval> $approvals
 */
#[Fillable([])]
#[UseFactory(TaskActionFactory::class)]
class TaskAction extends Model
{
    /** @use HasFactory<TaskActionFactory> */
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
            'type' => ActionType::class,
            'executor' => Executor::class,
            'status' => ActionStatus::class,
            'config' => 'array',
            'is_required' => 'boolean',
            'requires_approval' => 'boolean',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<PromptTemplate, $this>
     */
    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    /**
     * @return BelongsTo<CatalogAction, $this>
     */
    public function catalogAction(): BelongsTo
    {
        return $this->belongsTo(CatalogAction::class);
    }

    /**
     * @return HasMany<ActionRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ActionRun::class)->latest('started_at');
    }

    /**
     * @return BelongsTo<ActionRun, $this>
     */
    public function lastRun(): BelongsTo
    {
        return $this->belongsTo(ActionRun::class, 'last_run_id');
    }

    /**
     * @return HasMany<Evidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    /**
     * @return MorphMany<Approval, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'subject');
    }
}
