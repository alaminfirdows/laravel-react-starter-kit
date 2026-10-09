<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Enums\PackReviewStatus;
use App\Domain\Catalog\Models\Pack;
use InvalidArgumentException;

/**
 * Admin decision on a public community pack. Approved packs are published
 * for every workspace; rejected packs stay with the owner, with the note.
 */
class ReviewCommunityPack
{
    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Pack $pack, PackReviewStatus $decision, ?string $note, Actor $actor): Pack
    {
        if ($pack->review_status !== PackReviewStatus::Pending || $pack->ownerWorkspace === null) {
            throw new InvalidArgumentException('Only pending community packs can be reviewed.');
        }

        if ($decision === PackReviewStatus::Pending) {
            throw new InvalidArgumentException('Pick approve or reject.');
        }

        $pack->forceFill([
            'review_status' => $decision,
            'review_note' => $note,
            'status' => $decision === PackReviewStatus::Approved ? CatalogStatus::Published : CatalogStatus::Draft,
        ])->save();

        $this->activity->record('pack.reviewed', $pack->ownerWorkspace, [
            'pack' => $pack->key,
            'name' => $pack->name,
            'decision' => $decision->value,
        ], $actor);

        return $pack;
    }
}
