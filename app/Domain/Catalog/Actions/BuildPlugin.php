<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Data\PluginBuildData;
use App\Domain\Catalog\Data\SkillFileData;
use App\Domain\Catalog\Support\SkillFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Assembles the Claude plugin (manifest, remote connector, skills) and zips it.
 */
class BuildPlugin
{
    public const string NAME = 'founder-os';

    public const string DESCRIPTION = 'Work on your Founder OS tasks with Claude: connector plus task skills.';

    public function handle(string $skillsDirectory, string $outputDirectory, string $zipDirectory, string $version, string $connectorUrl): PluginBuildData
    {
        $skills = array_values(array_filter(
            array_map(SkillFile::read(...), SkillFile::find($skillsDirectory)),
            fn (SkillFileData $skill): bool => $skill->inPlugin,
        ));

        File::deleteDirectory($outputDirectory);
        File::ensureDirectoryExists("{$outputDirectory}/.claude-plugin");

        $this->writeJson("{$outputDirectory}/.claude-plugin/plugin.json", [
            'name' => self::NAME,
            'version' => $version,
            'description' => self::DESCRIPTION,
            'author' => ['name' => config('app.name')],
            'homepage' => config('app.url'),
        ]);

        $this->writeJson("{$outputDirectory}/.mcp.json", [
            'mcpServers' => [self::NAME => ['type' => 'http', 'url' => $connectorUrl]],
        ]);

        foreach ($skills as $skill) {
            File::copyDirectory($skill->directory, "{$outputDirectory}/skills/{$skill->name}");
        }

        File::ensureDirectoryExists($zipDirectory);
        $zipPath = "{$zipDirectory}/".self::NAME."-{$version}.zip";
        $this->zip($outputDirectory, $zipPath);

        return new PluginBuildData(
            directory: $outputDirectory,
            zipPath: $zipPath,
            version: $version,
            skills: array_map(fn (SkillFileData $skill): string => $skill->name, $skills),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * Zip with a top-level `founder-os/` folder, dotfiles included.
     */
    private function zip(string $directory, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot write {$zipPath}");
        }

        foreach (File::allFiles($directory, hidden: true) as $file) {
            $zip->addFile($file->getPathname(), self::NAME.'/'.$file->getRelativePathname());
        }

        $zip->close();
    }
}
