<?php

namespace App\Domain\Catalog\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\PublishCatalogTask;
use App\Domain\Catalog\Models\CatalogTask;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CatalogTaskPublicationController extends Controller
{
    public function store(CatalogTask $catalogTask, PublishCatalogTask $publish): RedirectResponse
    {
        $publish->handle($catalogTask);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog task published.')]);

        return back();
    }
}
