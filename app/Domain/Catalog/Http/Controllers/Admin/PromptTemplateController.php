<?php

namespace App\Domain\Catalog\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\SavePromptTemplate;
use App\Domain\Catalog\Http\Requests\SavePromptTemplateRequest;
use App\Domain\Catalog\Http\Resources\PromptTemplateResource;
use App\Domain\Catalog\Models\PromptTemplate;
use App\Domain\Prompt\Enums\DeepLinkTarget;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PromptTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/prompts/index', [
            'prompts' => PromptTemplateResource::collection(PromptTemplate::query()->orderBy('title')->get()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prompts/edit', [
            'prompt' => null,
            'targetOptions' => DeepLinkTarget::options(),
        ]);
    }

    public function store(SavePromptTemplateRequest $request, SavePromptTemplate $save): RedirectResponse
    {
        $prompt = $save->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prompt created.')]);

        return to_route('admin.prompts.edit', $prompt);
    }

    public function edit(PromptTemplate $promptTemplate): Response
    {
        return Inertia::render('admin/prompts/edit', [
            'prompt' => PromptTemplateResource::make($promptTemplate),
            'targetOptions' => DeepLinkTarget::options(),
        ]);
    }

    public function update(SavePromptTemplateRequest $request, PromptTemplate $promptTemplate, SavePromptTemplate $save): RedirectResponse
    {
        $save->handle($request->toData(), $promptTemplate);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Prompt saved.')]);

        return back();
    }
}
