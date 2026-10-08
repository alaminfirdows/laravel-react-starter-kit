<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/index', [
            'counts' => [
                'tasks' => CatalogTask::query()->count(),
                'actions' => CatalogAction::query()->count(),
                'prompts' => PromptTemplate::query()->count(),
                'packs' => Pack::query()->count(),
            ],
        ]);
    }
}
