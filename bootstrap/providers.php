<?php

use App\Domain\Catalog\Providers\CatalogServiceProvider;
use App\Domain\Workspace\Providers\WorkspaceServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    WorkspaceServiceProvider::class,
    CatalogServiceProvider::class,
];
