<?php

namespace App\Domain\Project\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Project\Enums\ProjectSetupStep;
use App\Domain\Project\Jobs\RebuildContextSnapshotJob;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Arr;

class UpdateProjectSetup
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Project $project, ProjectSetupStep $step, array $data): Project
    {
        $project->fill(Arr::only($data, $step->fields()));
        $changed = array_keys($project->getDirty());
        $project->save();

        if ($changed !== []) {
            $this->activity->record('project.updated', $project, ['step' => $step->value, 'changed' => $changed]);
            RebuildContextSnapshotJob::debounce($project->id);
        }

        return $project;
    }
}
