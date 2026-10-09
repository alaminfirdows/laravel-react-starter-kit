<?php

namespace App\Domain\Catalog\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Actions\SaveCommunityPack;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Enums\PackVisibility;
use App\Domain\Catalog\Http\Requests\SaveCommunityPackRequest;
use App\Domain\Catalog\Http\Resources\CommunityPackResource;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The current workspace's community packs.
 */
class CommunityPackController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('view', $workspace);

        return Inertia::render('packs/index', [
            'packs' => CommunityPackResource::collection($workspace->packs()->withCount('items')->orderBy('name')->get()),
            'can' => ['create' => $request->user()->can('create', Pack::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Pack::class);

        return Inertia::render('packs/edit', [...$this->formOptions(), 'pack' => null]);
    }

    public function store(SaveCommunityPackRequest $request, Workspace $workspace, SaveCommunityPack $save): RedirectResponse
    {
        $pack = $save->handle($workspace, $request->toData(), Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->savedMessage($pack)]);

        return to_route('packs.edit', ['pack' => $pack->key]);
    }

    public function edit(Workspace $workspace, Pack $pack): Response
    {
        Gate::authorize('update', $pack);

        return Inertia::render('packs/edit', [
            ...$this->formOptions(),
            'pack' => CommunityPackResource::make($pack->load('items.catalogTask')),
        ]);
    }

    public function update(SaveCommunityPackRequest $request, Workspace $workspace, Pack $pack, SaveCommunityPack $save): RedirectResponse
    {
        $save->handle($workspace, $request->toData(), Actor::user($request->user()), $pack);

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->savedMessage($pack)]);

        return back();
    }

    private function savedMessage(Pack $pack): string
    {
        return $pack->visibility === PackVisibility::Public
            ? __('Pack saved and sent for review.')
            : __('Pack saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'phaseOptions' => ProjectPhase::options(),
            'visibilityOptions' => PackVisibility::options(),
            'rootTasks' => CatalogTask::query()
                ->whereNull('parent_id')
                ->where('status', CatalogStatus::Published)
                ->with('category:id,name')
                ->orderBy('category_id')
                ->orderBy('sort_order')
                ->get(['id', 'key', 'title', 'category_id', 'status'])
                ->map(fn (CatalogTask $task): array => [
                    'key' => $task->key,
                    'title' => $task->title,
                    'category' => $task->category->name,
                    'status' => $task->status->value,
                ])
                ->all(),
        ];
    }
}
