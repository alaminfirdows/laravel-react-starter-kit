<?php

namespace App\Domain\Project\Models;

use App\Domain\Catalog\Models\Pack;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $project_id
 * @property int $pack_id
 * @property int $pack_version
 * @property string|null $applied_by
 * @property CarbonImmutable $applied_at
 */
#[Fillable(['project_id', 'pack_id', 'pack_version', 'applied_by', 'applied_at'])]
class ProjectPack extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'applied_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Pack, $this>
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }
}
