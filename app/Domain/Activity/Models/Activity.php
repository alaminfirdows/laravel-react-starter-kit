<?php

namespace App\Domain\Activity\Models;

use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string|null $project_id
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string|null $client_name
 * @property ActivityChannel $channel
 * @property string $event
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $properties
 */
#[Fillable(['workspace_id', 'project_id', 'actor_type', 'actor_id', 'client_name', 'channel', 'event', 'subject_type', 'subject_id', 'properties', 'ip'])]
class Activity extends Model
{
    use BelongsToWorkspace;

    public const null UPDATED_AT = null;

    protected $table = 'activity_log';

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'channel' => ActivityChannel::class,
            'properties' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
