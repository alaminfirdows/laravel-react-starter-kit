<?php

namespace App\Domain\Catalog\Console;

use App\Domain\Catalog\Actions\ImportCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:import {path=database/seeders/catalog : Directory with categories.yaml, prompts.yaml, packs.yaml and tasks/*.yaml} {--skills=resources/skills : Directory with <skill>/SKILL.md folders}')]
#[Description('Import the founder catalog from YAML')]
class ImportCatalogCommand extends Command
{
    public function handle(ImportCatalog $import): int
    {
        $counts = $import->handle(
            $this->absolute((string) $this->argument('path')),
            $this->absolute((string) $this->option('skills')),
        );

        $this->components->info(sprintf(
            'Imported %d categories, %d skills, %d resources, %d tasks, %d actions, %d prompts, %d packs.',
            ...array_values($counts),
        ));

        return self::SUCCESS;
    }

    private function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
