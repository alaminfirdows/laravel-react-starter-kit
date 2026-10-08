<?php

namespace App\Domain\Activity\Http\Controllers;

use App\Domain\Activity\Http\Requests\ActivityFilterRequest;
use App\Domain\Activity\Http\Resources\ActivityResource;
use App\Domain\Activity\Queries\ActivityFeed;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceActivityController extends Controller
{
    public function index(ActivityFilterRequest $request, Workspace $workspace, ActivityFeed $feed): Response
    {
        Gate::authorize('update', $workspace);

        $filters = $request->filters();

        return Inertia::render('workspace/settings/activity', [
            'activity' => ActivityResource::collection($feed->handle($workspace, $filters)),
            'filters' => $filters->toArray(),
            'options' => fn (): array => $feed->options($workspace),
        ]);
    }
}
