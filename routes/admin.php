<?php

use App\Domain\Catalog\Http\Controllers\Admin\CatalogActionController;
use App\Domain\Catalog\Http\Controllers\Admin\CatalogTaskController;
use App\Domain\Catalog\Http\Controllers\Admin\CatalogTaskPublicationController;
use App\Domain\Catalog\Http\Controllers\Admin\CommunityPackReviewController;
use App\Domain\Catalog\Http\Controllers\Admin\PackController;
use App\Domain\Catalog\Http\Controllers\Admin\PromptTemplateController;
use App\Http\Controllers\Admin\AdminDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Platform admin area: catalog authoring, pack review, analytics.
*/
Route::middleware(['auth', 'verified', 'can:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('index');

        Route::get('catalog', [CatalogTaskController::class, 'index'])->name('catalog.index');
        Route::get('catalog/create', [CatalogTaskController::class, 'create'])->name('catalog.create');
        Route::post('catalog', [CatalogTaskController::class, 'store'])->name('catalog.store');
        Route::get('catalog/{catalogTask:key}/edit', [CatalogTaskController::class, 'edit'])->name('catalog.edit');
        Route::patch('catalog/{catalogTask:key}', [CatalogTaskController::class, 'update'])->name('catalog.update');
        Route::post('catalog/{catalogTask:key}/publish', [CatalogTaskPublicationController::class, 'store'])->name('catalog.publish');
        Route::post('catalog/{catalogTask:key}/actions', [CatalogActionController::class, 'store'])->name('catalog.actions.store');
        Route::patch('catalog/{catalogTask:key}/actions/{catalogAction:key}', [CatalogActionController::class, 'update'])
            ->scopeBindings()
            ->name('catalog.actions.update');

        Route::resource('prompts', PromptTemplateController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['prompts' => 'promptTemplate:key']);

        Route::resource('packs', PackController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['packs' => 'pack:key']);

        Route::get('community-packs', [CommunityPackReviewController::class, 'index'])->name('community-packs.index');
        Route::post('community-packs/{pack:key}/review', [CommunityPackReviewController::class, 'store'])->name('community-packs.review.store');
    });
