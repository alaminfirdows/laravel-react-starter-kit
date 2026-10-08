<?php

namespace App\Domain\Project\Models;

use App\Domain\Media\Models\Media;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $project_id
 * @property string|null $logo_media_id
 * @property string|null $voice_md
 * @property-read Media|null $logo
 */
#[Fillable(['colors', 'fonts', 'voice_md', 'tone', 'logo_media_id', 'logo_variants', 'socials'])]
class ProjectBrand extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'fonts' => 'array',
            'tone' => 'array',
            'logo_variants' => 'array',
            'socials' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }
}
