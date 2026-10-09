<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Data\CommunityPackData;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Enums\PackReviewStatus;
use App\Domain\Catalog\Enums\PackVisibility;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create/edit a workspace's community pack. Private packs are live for the
 * owner at once. Public packs go (back) to review on every save and stay
 * hidden from other workspaces until an admin approves them.
 */
class SaveCommunityPack
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Workspace $workspace, CommunityPackData $data, Actor $actor, ?Pack $pack = null): Pack
    {
        $pack ??= new Pack;

        return DB::transaction(function () use ($workspace, $data, $actor, $pack): Pack {
            $isPublic = $data->visibility === PackVisibility::Public;

            $pack->forceFill([
                'key' => $pack->key ?? 'community-'.Str::lower((string) Str::ulid()),
                'owner_workspace_id' => $workspace->id,
                'name' => $data->name,
                'description_md' => $data->descriptionMd,
                'audience' => ['phase' => $data->phase->value],
                'is_default' => false,
                'visibility' => $data->visibility,
                'status' => $isPublic ? CatalogStatus::Draft : CatalogStatus::Published,
                'review_status' => $isPublic ? PackReviewStatus::Pending : null,
                'review_note' => null,
                'version' => $pack->exists ? $pack->version + 1 : 1,
            ])->save();

            $pack->items()->delete();
            $pack->items()->createMany(array_map(fn (int $id, int $i): array => [
                'catalog_task_id' => $id,
                'include_subtree' => true,
                'sort_order' => $i,
            ], $data->catalogTaskIds, array_keys($data->catalogTaskIds)));

            $this->activity->record($pack->wasRecentlyCreated ? 'pack.created' : 'pack.updated', $workspace, [
                'pack' => $pack->key,
                'name' => $pack->name,
                'visibility' => $pack->visibility->value,
            ], $actor);

            return $pack;
        });
    }
}
