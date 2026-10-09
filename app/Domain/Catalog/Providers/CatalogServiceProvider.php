<?php

namespace App\Domain\Catalog\Providers;

use App\Domain\Catalog\Console\BuildPluginCommand;
use App\Domain\Catalog\Console\ExportCatalogCommand;
use App\Domain\Catalog\Console\ImportCatalogCommand;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportCatalogCommand::class, ExportCatalogCommand::class, BuildPluginCommand::class]);
        }
    }
}
