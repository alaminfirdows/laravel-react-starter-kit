<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateWorkspace
{
    public function __construct(protected ActivityRecorder $activity) {}

    /**
     * Rename keeps the slug; the slug changes only when given explicitly.
     *
     * @param  array{name?: string, slug?: string}  $attributes
     */
    public function handle(Workspace $workspace, array $attributes): Workspace
    {
        if (isset($attributes['name'])) {
            $workspace->name = $attributes['name'];
        }

        if (isset($attributes['slug'])) {
            $workspace->slug = Str::lower($attributes['slug']);
        }

        return DB::transaction(function () use ($workspace): Workspace {
            $changes = collect($workspace->getDirty())
                ->only(['name', 'slug'])
                ->map(fn (mixed $to, string $field): array => ['from' => $workspace->getOriginal($field), 'to' => $to])
                ->all();

            $workspace->save();

            if ($changes !== []) {
                $this->activity->record('workspace.updated', $workspace, ['changes' => $changes], Actor::current());
            }

            return $workspace;
        });
    }
}
