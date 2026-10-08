<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Data\PackData;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PackItem;
use App\Domain\Catalog\Support\Versioning;
use Illuminate\Support\Facades\DB;

/**
 * Admin create/edit of a pack. One default pack per phase: making this pack
 * the default clears the flag on the others of its phase.
 */
class SavePack
{
    public function handle(PackData $data, ?Pack $pack = null): Pack
    {
        $pack ??= new Pack;

        return DB::transaction(function () use ($data, $pack): Pack {
            $items = array_map(fn (array $item, int $i): array => [...$item, 'sort_order' => $i], $data->items, array_keys($data->items));
            $itemsChanged = $this->items($pack) !== $items;

            Versioning::edit($pack, [
                'key' => $data->key,
                'name' => $data->name,
                'description_md' => $data->descriptionMd,
                'audience' => [...($pack->audience ?? []), 'phase' => $data->phase->value],
                'is_default' => $data->isDefault,
                'status' => $data->status,
            ], force: $itemsChanged);

            if ($itemsChanged) {
                $pack->items()->delete();
                $pack->items()->createMany($items);
            }

            if ($data->isDefault) {
                Pack::query()
                    ->whereKeyNot($pack->id)
                    ->where('is_default', true)
                    ->where('audience->phase', $data->phase->value)
                    ->update(['is_default' => false]);
            }

            return $pack;
        });
    }

    /**
     * @return list<array{catalog_task_id: int, include_subtree: bool, sort_order: int}>
     */
    private function items(Pack $pack): array
    {
        if (! $pack->exists) {
            return [];
        }

        return array_values($pack->items()->get()
            ->map(fn (PackItem $item): array => [
                'catalog_task_id' => $item->catalog_task_id,
                'include_subtree' => $item->include_subtree,
                'sort_order' => $item->sort_order,
            ])
            ->all());
    }
}
