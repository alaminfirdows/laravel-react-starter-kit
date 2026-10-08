<?php

namespace App\Domain\Catalog\Console;

use App\Domain\Catalog\Actions\BuildPlugin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('plugin:build {--release=1.0.0 : Plugin version} {--skills=resources/skills : Directory with <skill>/SKILL.md folders} {--output=plugin : Build directory (replaced on every build)}')]
#[Description('Build the Founder OS Claude plugin (manifest, connector, skills) and zip it')]
class BuildPluginCommand extends Command
{
    public function handle(BuildPlugin $build): int
    {
        try {
            $result = $build->handle(
                skillsDirectory: $this->absolute((string) $this->option('skills')),
                outputDirectory: $this->absolute((string) $this->option('output')),
                zipDirectory: storage_path('app/plugins'),
                version: (string) $this->option('release'),
                connectorUrl: route('mcp.founder'),
            );
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Built %s with %d skills: %s', $result->zipPath, count($result->skills), implode(', ', $result->skills)));

        return self::SUCCESS;
    }

    private function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
