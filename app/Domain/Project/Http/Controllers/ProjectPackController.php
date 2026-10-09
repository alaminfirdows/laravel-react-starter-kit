<?php

namespace App\Domain\Project\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Queries\AvailablePacks;
use App\Domain\Project\Actions\ApplyPack;
use App\Domain\Project\Http\Requests\AddPackRequest;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProjectPackController extends Controller
{
    public function store(AddPackRequest $request, Workspace $workspace, Project $project, AvailablePacks $availablePacks, ApplyPack $applyPack): RedirectResponse
    {
        $pack = $request->pack($availablePacks);
        $created = $applyPack->handle($project, $pack, Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('Added :name with :count task.|Added :name with :count tasks.', $created, ['name' => $pack->name, 'count' => $created])]);

        return back();
    }
}
