<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Activity\ActivityRecorder;
use App\Domain\Activity\Data\Actor;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateWorkspaceLogo
{
    public const string DISK = 'public';

    public function __construct(protected ActivityRecorder $activity) {}

    public function handle(Workspace $workspace, UploadedFile $logo): Workspace
    {
        $previous = $workspace->logo_path;

        $path = $logo->store("workspace-logos/{$workspace->id}", self::DISK);

        DB::transaction(function () use ($workspace, $path): void {
            $workspace->forceFill(['logo_path' => $path])->save();

            $this->activity->record('workspace.logo_updated', $workspace, ['removed' => false], Actor::current());
        });

        if ($previous && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }

        return $workspace;
    }

    public function remove(Workspace $workspace): Workspace
    {
        if ($workspace->logo_path) {
            Storage::disk(self::DISK)->delete($workspace->logo_path);
        }

        DB::transaction(function () use ($workspace): void {
            $workspace->forceFill(['logo_path' => null])->save();

            $this->activity->record('workspace.logo_updated', $workspace, ['removed' => true], Actor::current());
        });

        return $workspace;
    }
}
