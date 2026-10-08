<?php

namespace App\Domain\Comment\Models;

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Comment on a task (DATA_MODEL §C). Author is a user, an agent acting for a user, or the system.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $project_id
 * @property string $commentable_type
 * @property string $commentable_id
 * @property ActorType $author_type
 * @property string|null $author_id
 * @property string|null $client_name
 * @property string $body_md
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $resolved_by_id
 * @property CarbonImmutable|null $created_at
 * @property-read Project $project
 * @property-read Model $commentable
 * @property-read User|null $author
 * @property-read User|null $resolvedBy
 */
#[Fillable(['workspace_id', 'project_id', 'author_type', 'author_id', 'client_name', 'body_md'])]
#[UseFactory(CommentFactory::class)]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use BelongsToWorkspace, HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'author_type' => ActorType::class,
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
