<?php

namespace App\Domain\Catalog\Console;

use App\Domain\Catalog\Actions\ImportCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:import {path=database/seeders/catalog : Directory with categories.yaml, prompts.yaml, packs.yaml and tasks/*.yaml}')]
#[Description('Import the founder catalog from YAML')]
class ImportCatalogCommand extends Command
{
    public function handle(ImportCatalog $import): int
    {
        $path = (string) $this->argument('path');
        $counts = $import->handle(str_starts_with($path, '/') ? $path : base_path($path));

        $this->components->info(sprintf(
            'Imported %d categories, %d tasks, %d actions, %d prompts, %d packs.',
            ...array_values($counts),
        ));

        return self::SUCCESS;
    }
}
