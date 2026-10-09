<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\Http\Resources\TaskCompletionStatResource;
use App\Domain\Analytics\Http\Resources\TaskDropOffResource;
use App\Domain\Analytics\Queries\DropOffByTask;
use App\Domain\Analytics\Queries\TaskCompletionStats;
use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Analytics window in days.
     */
    public const int WINDOW_DAYS = 90;

    public function index(TaskCompletionStats $completionStats, DropOffByTask $dropOff): Response
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        return Inertia::render('admin/index', [
            'counts' => [
                'tasks' => CatalogTask::query()->count(),
                'actions' => CatalogAction::query()->count(),
                'prompts' => PromptTemplate::query()->count(),
                'packs' => Pack::query()->official()->count(),
            ],
            'windowDays' => self::WINDOW_DAYS,
            'completionStats' => Inertia::defer(fn () => TaskCompletionStatResource::collection($completionStats->handle($since))),
            'dropOff' => Inertia::defer(fn () => TaskDropOffResource::collection($dropOff->handle($since))),
        ]);
    }
}
