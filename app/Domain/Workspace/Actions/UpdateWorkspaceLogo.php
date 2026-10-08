<?php

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateWorkspaceLogo
{
    public const string DISK = 'public';

    public function handle(Workspace $workspace, UploadedFile $logo): Workspace
    {
        $previous = $workspace->logo_path;

        $path = $logo->store("workspace-logos/{$workspace->id}", self::DISK);

        $workspace->forceFill(['logo_path' => $path])->save();

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

        $workspace->forceFill(['logo_path' => null])->save();

        return $workspace;
    }
}
