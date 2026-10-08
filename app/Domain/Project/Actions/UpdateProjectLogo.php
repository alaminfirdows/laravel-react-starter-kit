<?php

namespace App\Domain\Project\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Domain\Project\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateProjectLogo
{
    public const string DISK = 'public';

    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Project $project, UploadedFile $logo): Media
    {
        return DB::transaction(function () use ($project, $logo): Media {
            $brand = $project->brand()->firstOrCreate();
            $previous = $brand->logo;
            [$width, $height] = getimagesize($logo->getRealPath()) ?: [null, null];

            $media = new Media;
            $media->forceFill([
                'workspace_id' => $project->workspace_id,
                'project_id' => $project->id,
                'owner_type' => $brand->getMorphClass(),
                'owner_id' => $brand->id,
                'disk' => self::DISK,
                'path' => $logo->store("project-logos/{$project->id}", self::DISK),
                'mime' => $logo->getMimeType(),
                'size' => $logo->getSize(),
                'width' => $width,
                'height' => $height,
                'kind' => MediaKind::Image,
                'checksum' => hash_file('sha256', $logo->getRealPath()),
            ])->save();

            $brand->forceFill(['logo_media_id' => $media->id])->save();

            if ($previous !== null) {
                Storage::disk($previous->disk)->delete($previous->path);
                $previous->delete();
            }

            $this->activity->record('project.logo_updated', $project);

            return $media;
        });
    }
}
