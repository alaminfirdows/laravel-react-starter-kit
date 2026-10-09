<?php

namespace App\Domain\Catalog\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\SaveCatalogTask;
use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Http\Requests\SaveCatalogTaskRequest;
use App\Domain\Catalog\Http\Resources\AdminCatalogTaskResource;
use App\Domain\Catalog\Http\Resources\CatalogActionResource;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Catalog\Queries\CatalogTree;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use App\Domain\Task\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CatalogTaskController extends Controller
{
    public function index(CatalogTree $tree): Response
    {
        return Inertia::render('admin/catalog/index', [
            'categories' => $tree->handle(),
        ]);
    }

    public function create(Request $request): Response
    {
        $parent = $request->filled('parent')
            ? CatalogTask::query()->where('key', $request->string('parent')->toString())->firstOrFail()
            : null;

        return Inertia::render('admin/catalog/edit', [
            ...$this->formOptions(),
            'task' => null,
            'parent' => $parent ? ['key' => $parent->key, 'title' => $parent->title] : null,
            'actions' => [],
        ]);
    }

    public function store(SaveCatalogTaskRequest $request, SaveCatalogTask $save): RedirectResponse
    {
        try {
            $task = $save->handle($request->toData());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['parent' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft task created.')]);

        return to_route('admin.catalog.edit', $task);
    }

    public function edit(CatalogTask $catalogTask): Response
    {
        $catalogTask->load(['parent', 'actions.promptTemplate']);

        return Inertia::render('admin/catalog/edit', [
            ...$this->formOptions(),
            'task' => AdminCatalogTaskResource::make($catalogTask),
            'parent' => $catalogTask->parent ? ['key' => $catalogTask->parent->key, 'title' => $catalogTask->parent->title] : null,
            'actions' => CatalogActionResource::collection($catalogTask->actions),
        ]);
    }

    public function update(SaveCatalogTaskRequest $request, CatalogTask $catalogTask, SaveCatalogTask $save): RedirectResponse
    {
        $save->handle($request->toData(), $catalogTask);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog task saved.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categoryOptions' => CatalogCategory::query()->orderBy('sort_order')->get()
                ->map(fn (CatalogCategory $category): array => ['value' => (string) $category->id, 'label' => $category->name])
                ->all(),
            'priorityOptions' => TaskPriority::options(),
            'statusOptions' => CatalogStatus::options(),
            'actionTypeOptions' => ActionType::options(),
            'executorOptions' => Executor::options(),
            'promptOptions' => Inertia::optional(fn (): array => PromptTemplate::query()->orderBy('title')->get(['key', 'title'])
                ->map(fn (PromptTemplate $prompt): array => ['value' => $prompt->key, 'label' => $prompt->title])
                ->all()),
        ];
    }
}
