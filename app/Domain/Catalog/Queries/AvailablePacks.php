<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Extra packs a project can add: visible to its workspace (official,
 * reviewed public community, or own), not the phase default, made for the
 * project phase, and not applied yet.
 */
class AvailablePacks
{
    /**
     * @return Builder<Pack>
     */
    public function query(Project $project): Builder
    {
        return Pack::query()
            ->visibleTo($project->workspace)
            ->where('is_default', false)
            ->where('audience->phase', $project->phase->value)
            ->whereNotIn('id', $project->packs()->select('pack_id'));
    }

    /**
     * @return Collection<int, Pack>
     */
    public function handle(Project $project): Collection
    {
        return $this->query($project)->withCount('items')->orderBy('name')->get();
    }
}
