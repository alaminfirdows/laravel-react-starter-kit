<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pack_id
 * @property int $catalog_task_id
 * @property bool $include_subtree
 * @property int $sort_order
 * @property-read Pack $pack
 * @property-read CatalogTask $catalogTask
 */
#[Fillable(['pack_id', 'catalog_task_id', 'include_subtree', 'sort_order'])]
class PackItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'include_subtree' => 'boolean',
        ];
    }

    /** @return BelongsTo<Pack, $this> */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    /** @return BelongsTo<CatalogTask, $this> */
    public function catalogTask(): BelongsTo
    {
        return $this->belongsTo(CatalogTask::class);
    }
}
