<?php

namespace App\Domain\Task\Models;

use App\Domain\Activity\Enums\ActorType;
use App\Domain\Project\Models\Project;
use App\Domain\Task\Enums\RunChannel;
use App\Domain\Task\Enums\RunStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ActionRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt at an action: a copied prompt, a deep link, an MCP session or an in-app AI job.
 *
 * @property string $id
 * @property string $task_action_id
 * @property string $task_id
 * @property string $project_id
 * @property RunChannel $channel
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string|null $client_name
 * @property string|null $rendered_prompt
 * @property RunStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property string|null $output_md
 * @property array<string, mixed>|null $output
 * @property string|null $error
 * @property array<string, mixed>|null $usage
 * @property-read TaskAction $action
 * @property-read Task $task
 * @property-read Project $project
 */
#[Fillable(['task_action_id', 'task_id', 'project_id', 'channel', 'actor_type', 'actor_id', 'client_name', 'rendered_prompt', 'status', 'started_at', 'finished_at', 'output_md', 'output', 'error', 'usage'])]
#[UseFactory(ActionRunFactory::class)]
class ActionRun extends Model
{
    /** @use HasFactory<ActionRunFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'channel' => RunChannel::class,
            'actor_type' => ActorType::class,
            'status' => RunStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'output' => 'array',
            'usage' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TaskAction, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(TaskAction::class, 'task_action_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
