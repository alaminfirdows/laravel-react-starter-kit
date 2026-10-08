<?php

namespace Database\Seeders;

use App\Domain\Catalog\Actions\ImportCatalog;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(ImportCatalog $import): void
    {
        $import->handle(database_path('seeders/catalog'), resource_path('skills'));
    }
}
