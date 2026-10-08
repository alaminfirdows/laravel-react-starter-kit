<?php

namespace App\Domain\Project\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProject
{
    public function __construct(
        protected ApplyPack $applyPack,
        protected ActivityRecorder $activity,
    ) {}

    public function handle(User $owner, ProjectPhase $phase, string $name): Project
    {
        $actor = Actor::user($owner);

        return DB::transaction(function () use ($owner, $phase, $name, $actor): Project {
            $project = new Project(['name' => $name]);
            $project->forceFill([
                'owner_id' => $owner->id,
                'slug' => $this->uniqueSlug($name),
                'phase' => $phase,
                'status' => ProjectStatus::Draft,
            ])->save();

            $this->activity->record('project.created', $project, ['phase' => $phase->value], $actor);

            if ($pack = Pack::defaultForPhase($phase)) {
                $this->applyPack->handle($project, $pack, $actor);
            }

            return $project;
        });
    }

    /**
     * Trashed projects keep their slug reserved.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;

        for ($i = 2; Project::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
