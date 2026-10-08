<?php

namespace App\Domain\Activity\Queries;

use App\Domain\Activity\Data\ActivityFilters;
use App\Domain\Activity\Enums\ActivityChannel;
use App\Domain\Activity\Enums\ActorType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Filtered, newest-first audit log of a workspace or one project.
 */
class ActivityFeed
{
    public const int PER_PAGE = 50;

    /**
     * @return Paginator<int, Activity>
     */
    public function handle(Workspace $workspace, ActivityFilters $filters, ?string $projectId = null): Paginator
    {
        return $this->scoped($workspace, $projectId)
            ->with(['actor:id,name', 'project:id,name,slug'])
            ->when($filters->actor === ActivityFilters::SYSTEM_ACTOR, fn (Builder $query) => $query->where('actor_type', ActorType::System))
            ->when($filters->actor !== null && $filters->actor !== ActivityFilters::SYSTEM_ACTOR, fn (Builder $query) => $query->where('actor_id', $filters->actor))
            ->when($filters->channel, fn (Builder $query, ActivityChannel $channel) => $query->where('channel', $channel))
            ->when($filters->event, fn (Builder $query, string $event) => $query->where('event', $event))
            ->latest('id')
            ->simplePaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Select items for the filter bar.
     *
     * @return array{actors: list<array{value: string, label: string}>, channels: list<array{value: string, label: string}>, events: list<array{value: string, label: string}>}
     */
    public function options(Workspace $workspace, ?string $projectId = null): array
    {
        $actors = $workspace->members()->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn ($user): array => ['value' => (string) $user->id, 'label' => (string) $user->name])
            ->push(['value' => ActivityFilters::SYSTEM_ACTOR, 'label' => __('System')])
            ->values()->all();

        $events = $this->scoped($workspace, $projectId)->distinct()->orderBy('event')->pluck('event')
            ->map(fn (string $event): array => ['value' => $event, 'label' => Str::of($event)->replace(['.', '_'], ' ')->ucfirst()->value()])
            ->values()->all();

        return [
            'actors' => array_values($actors),
            'channels' => array_map(fn (ActivityChannel $channel): array => ['value' => $channel->value, 'label' => Str::upper($channel->value)], ActivityChannel::cases()),
            'events' => array_values($events),
        ];
    }

    /**
     * @return Builder<Activity>
     */
    private function scoped(Workspace $workspace, ?string $projectId): Builder
    {
        return Activity::withoutWorkspaceScope()
            ->where('workspace_id', $workspace->id)
            ->when($projectId, fn (Builder $query, string $projectId) => $query->where('project_id', $projectId));
    }
}
