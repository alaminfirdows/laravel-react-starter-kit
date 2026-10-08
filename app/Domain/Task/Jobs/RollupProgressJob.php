<?php

namespace App\Domain\Task\Jobs;

use App\Domain\Project\Models\Project;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Scopes\WorkspaceScope;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Caches mean leaf progress on `projects.progress_pct`. Dispatched with a 2 s delay and unique
 * per project, so a burst of task changes (an agent closing many actions) costs one query.
 */
class RollupProgressJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const int DEBOUNCE_SECONDS = 2;

    public int $uniqueFor = 60;

    public function __construct(public string $projectId) {}

    public static function debounce(string $projectId): void
    {
        self::dispatch($projectId)->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
    }

    public function uniqueId(): string
    {
        return $this->projectId;
    }

    public function handle(): void
    {
        $average = Task::withoutWorkspaceScope()
            ->where('project_id', $this->projectId)
            ->whereDoesntHave('children', fn (Builder $children) => $children->withoutGlobalScope(WorkspaceScope::class))
            ->avg('progress_pct');

        Project::withoutWorkspaceScope()
            ->whereKey($this->projectId)
            ->update(['progress_pct' => (int) round((float) $average)]);
    }
}
