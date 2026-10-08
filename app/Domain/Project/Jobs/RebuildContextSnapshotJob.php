<?php

namespace App\Domain\Project\Jobs;

use App\Domain\Project\Actions\BuildContextSnapshot;
use App\Domain\Project\Models\Project;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Debounced, unique per project: a burst of document or profile saves rebuilds the snapshot once.
 */
class RebuildContextSnapshotJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const int DEBOUNCE_SECONDS = 5;

    public int $uniqueFor = 60;

    public function __construct(public string $projectId) {}

    public static function debounce(string $projectId): void
    {
        self::dispatch($projectId)->delay(now()->addSeconds(self::DEBOUNCE_SECONDS))->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->projectId;
    }

    public function handle(BuildContextSnapshot $build): void
    {
        $project = Project::withoutWorkspaceScope()->find($this->projectId);

        if ($project !== null) {
            $build->handle($project);
        }
    }
}
