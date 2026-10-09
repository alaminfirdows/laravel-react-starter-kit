<?php

namespace App\Domain\Catalog\Console;

use App\Domain\Catalog\Actions\ExportCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:export {path=database/seeders/catalog : Directory to write prompts.yaml, packs.yaml and tasks/*.yaml}')]
#[Description('Write catalog edits made in the admin UI back to YAML')]
class ExportCatalogCommand extends Command
{
    public function handle(ExportCatalog $export): int
    {
        $path = (string) $this->argument('path');
        $counts = $export->handle(str_starts_with($path, '/') ? $path : base_path($path));

        $this->components->info(sprintf('Exported %d prompts, %d tasks, %d packs.', ...array_values($counts)));

        return self::SUCCESS;
    }
}
