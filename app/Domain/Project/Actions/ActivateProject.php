<?php

namespace App\Domain\Project\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Jobs\RebuildContextSnapshotJob;
use App\Domain\Project\Models\Project;
use Illuminate\Validation\ValidationException;

class ActivateProject
{
    /**
     * @var list<string>
     */
    public const array REQUIRED = ['name', 'one_liner', 'business_model', 'stage', 'primary_market'];

    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Project $project): Project
    {
        if (! $project->isDraft()) {
            return $project;
        }

        $missing = array_values(array_filter(
            self::REQUIRED,
            fn (string $field): bool => blank($project->getAttribute($field)),
        ));

        if ($missing !== []) {
            throw ValidationException::withMessages(
                array_fill_keys($missing, __('This field is required to finish setup.')),
            );
        }

        $project->forceFill(['status' => ProjectStatus::Active, 'activated_at' => now()])->save();
        $this->activity->record('project.activated', $project);
        RebuildContextSnapshotJob::debounce($project->id);

        return $project;
    }
}
