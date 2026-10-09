<?php

namespace App\Domain\Catalog\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\SaveCatalogAction;
use App\Domain\Catalog\Http\Requests\SaveCatalogActionRequest;
use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogTask;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CatalogActionController extends Controller
{
    public function store(SaveCatalogActionRequest $request, CatalogTask $catalogTask, SaveCatalogAction $save): RedirectResponse
    {
        $save->handle($catalogTask, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Action added.')]);

        return back();
    }

    public function update(SaveCatalogActionRequest $request, CatalogTask $catalogTask, CatalogAction $catalogAction, SaveCatalogAction $save): RedirectResponse
    {
        $save->handle($catalogTask, $request->toData(), $catalogAction);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Action saved.')]);

        return back();
    }
}
