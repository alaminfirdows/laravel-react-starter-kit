<?php

namespace App\Domain\Task\Models;

use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Comment\Models\Comment;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Enums\Verification;
use App\Domain\Task\Policies\TaskPolicy;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $project_id
 * @property int|null $catalog_task_id
 * @property int|null $catalog_version
 * @property string|null $parent_id
 * @property int $depth
 * @property int $sort_order
 * @property string|null $category_key
 * @property string $title
 * @property string|null $summary
 * @property string|null $body_md
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property CarbonImmutable|null $due_at
 * @property string|null $assignee_id
 * @property list<array<string, mixed>>|null $completion_criteria
 * @property list<array<string, mixed>>|null $expected_outputs
 * @property Verification $verification
 * @property int $progress_pct
 * @property string|null $blocked_reason
 * @property string|null $skipped_reason
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $completed_by_type
 * @property string|null $completed_by_id
 * @property bool $has_catalog_update
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read User|null $assignee
 * @property-read Task|null $parent
 * @property-read Collection<int, Task> $children
 * @property-read Collection<int, TaskAction> $actions
 * @property-read Collection<int, Task> $dependencies
 * @property-read Collection<int, Task> $dependents
 * @property-read Collection<int, Evidence> $evidence
 * @property-read Collection<int, ActionRun> $runs
 */
#[Fillable(['title', 'summary', 'body_md', 'body_doc', 'priority', 'due_at'])]
#[UseFactory(TaskFactory::class)]
#[UsePolicy(TaskPolicy::class)]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToWorkspace, HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'todo',
        'priority' => 'p2',
        'verification' => 'none',
        'depth' => 0,
        'progress_pct' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'verification' => Verification::class,
            'completion_criteria' => 'array',
            'expected_outputs' => 'array',
            'body_doc' => 'array',
            'has_catalog_update' => 'boolean',
            'due_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isLeaf(): bool
    {
        return array_key_exists('children_count', $this->attributes)
            ? (int) $this->attributes['children_count'] === 0
            : ! $this->children()->exists();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<CatalogTask, $this>
     */
    public function catalogTask(): BelongsTo
    {
        return $this->belongsTo(CatalogTask::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<TaskAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(TaskAction::class)->orderBy('sort_order');
    }

    /**
     * Tasks this task waits for.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_id')
            ->withPivot('kind');
    }

    /**
     * Tasks that wait for this task.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'depends_on_id', 'task_id')
            ->withPivot('kind');
    }

    /**
     * @return HasMany<Evidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    /**
     * @return HasMany<ActionRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ActionRun::class)->latest('started_at');
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest();
    }
}
