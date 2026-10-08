<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Knowledge\Enums\DocSource;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\DecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Decision log entry (DATA_MODEL §C).
 *
 * @property string $id
 * @property string $project_id
 * @property string|null $task_id
 * @property string $title
 * @property string $decision_md
 * @property string|null $rationale_md
 * @property list<string>|null $alternatives
 * @property string|null $owner_id
 * @property CarbonImmutable $decided_on
 * @property DocSource $source
 * @property CarbonImmutable|null $revisit_on
 * @property CarbonImmutable|null $created_at
 * @property-read Project $project
 * @property-read Task|null $task
 * @property-read User|null $owner
 */
#[Fillable(['project_id', 'workspace_id', 'task_id', 'title', 'decision_md', 'rationale_md', 'alternatives', 'owner_id', 'decided_on', 'source', 'revisit_on'])]
#[UseFactory(DecisionFactory::class)]
class Decision extends Model
{
    /** @use HasFactory<DecisionFactory> */
    use BelongsToWorkspace, HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'alternatives' => 'array',
            'decided_on' => 'immutable_date',
            'revisit_on' => 'immutable_date',
            'source' => DocSource::class,
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
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
