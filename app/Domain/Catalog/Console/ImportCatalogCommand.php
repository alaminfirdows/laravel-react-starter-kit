<?php

namespace App\Domain\Catalog\Console;

use App\Domain\Catalog\Actions\ImportCatalog;
use App\Domain\Catalog\Queries\PendingAdminEdits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalog:import {path=database/seeders/catalog : Directory with categories.yaml, prompts.yaml, packs.yaml and tasks/*.yaml} {--skills=resources/skills : Directory with <skill>/SKILL.md folders} {--check : Only report admin edits not yet exported} {--force : Import even if admin edits would be overwritten}')]
#[Description('Import the founder catalog from YAML')]
class ImportCatalogCommand extends Command
{
    public function handle(ImportCatalog $import, PendingAdminEdits $pendingEdits): int
    {
        $pending = $pendingEdits->handle();

        if ($pending !== [] && ($this->option('check') || ! $this->option('force'))) {
            $this->components->error(sprintf('%d admin edits are not exported to YAML. Run catalog:export first, or use --force to overwrite them.', count($pending)));
            $this->components->bulletList($pending);

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->components->info('Catalog DB has no unexported admin edits.');

            return self::SUCCESS;
        }

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
