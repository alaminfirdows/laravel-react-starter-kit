<?php

namespace App\Domain\Catalog\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\SavePack;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Http\Requests\SavePackRequest;
use App\Domain\Catalog\Http\Resources\PackResource;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/packs/index', [
            'packs' => PackResource::collection(Pack::query()->official()->withCount('items')->orderBy('name')->get()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/packs/edit', [
            ...$this->formOptions(),
            'pack' => null,
        ]);
    }

    public function store(SavePackRequest $request, SavePack $save): RedirectResponse
    {
        $pack = $save->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pack created.')]);

        return to_route('admin.packs.edit', $pack);
    }

    public function edit(Pack $pack): Response
    {
        return Inertia::render('admin/packs/edit', [
            ...$this->formOptions(),
            'pack' => PackResource::make($pack->load('items.catalogTask')),
        ]);
    }

    public function update(SavePackRequest $request, Pack $pack, SavePack $save): RedirectResponse
    {
        $save->handle($request->toData(), $pack);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pack saved.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'phaseOptions' => ProjectPhase::options(),
            'statusOptions' => CatalogStatus::options(),
            'rootTasks' => CatalogTask::query()
                ->whereNull('parent_id')
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
