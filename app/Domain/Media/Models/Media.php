<?php

namespace App\Domain\Media\Models;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Workspace\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string|null $project_id
 * @property string $disk
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property MediaKind $kind
 * @property string $checksum
 */
#[Fillable(['workspace_id', 'project_id', 'owner_type', 'owner_id', 'disk', 'path', 'mime', 'size', 'width', 'height', 'alt', 'kind', 'checksum'])]
class Media extends Model
{
    use BelongsToWorkspace, HasUlids;

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
        ];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
