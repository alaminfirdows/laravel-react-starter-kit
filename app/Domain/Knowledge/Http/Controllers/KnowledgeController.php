<?php

namespace App\Domain\Knowledge\Http\Controllers;

use App\Domain\Activity\Data\Actor;
use App\Domain\Knowledge\Actions\SaveDocument;
use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Http\Requests\SaveKnowledgeDocumentRequest;
use App\Domain\Knowledge\Http\Resources\KnowledgeDocumentResource;
use App\Domain\Knowledge\Http\Resources\SearchResultResource;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Knowledge\Queries\HybridSearch;
use App\Domain\Project\Http\ProjectPageProps;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeController extends Controller
{
    public function index(Request $request, Workspace $workspace, Project $project, HybridSearch $search): Response
    {
        Gate::authorize('view', $project);

        $query = trim($request->string('q')->toString());

        return Inertia::render('projects/knowledge/index', [
            new ProjectPageProps($project),
            'documents' => KnowledgeDocumentResource::collection(
                $project->knowledgeDocuments()
                    ->select(['id', 'doc_type', 'title', 'status', 'source', 'version', 'embedded_at', 'updated_at'])
                    ->orderBy('doc_type')
                    ->latest('updated_at')
                    ->get(),
            ),
            'query' => $query,
            'results' => Inertia::optional(fn () => $query === ''
                ? []
                : SearchResultResource::collection($search->handle($project, $query))),
        ]);
    }

    public function create(Request $request, Workspace $workspace, Project $project): Response
    {
        Gate::authorize('update', $project);

        return $this->form($project, null, DocType::tryFrom($request->string('type')->toString()));
    }

    public function store(SaveKnowledgeDocumentRequest $request, Workspace $workspace, Project $project, SaveDocument $save): RedirectResponse
    {
        $document = $save->handle($project, $request->toData(), Actor::user($request->user()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document saved.')]);

        return to_route('projects.knowledge.show', ['project' => $project->slug, 'knowledgeDocument' => $document->id]);
    }

    public function show(Workspace $workspace, Project $project, KnowledgeDocument $knowledgeDocument): Response
    {
        Gate::authorize('view', $knowledgeDocument);

        $knowledgeDocument->load(['versions' => fn (HasMany $query) => $query->latest('version')]);

        return Inertia::render('projects/knowledge/show', [
            new ProjectPageProps($project),
            'document' => KnowledgeDocumentResource::make($knowledgeDocument),
        ]);
    }

    public function edit(Workspace $workspace, Project $project, KnowledgeDocument $knowledgeDocument): Response
    {
        Gate::authorize('update', $knowledgeDocument);

        return $this->form($project, $knowledgeDocument);
    }

    public function update(SaveKnowledgeDocumentRequest $request, Workspace $workspace, Project $project, KnowledgeDocument $knowledgeDocument, SaveDocument $save): RedirectResponse
    {
        $save->handle($project, $request->toData(), Actor::user($request->user()), $knowledgeDocument);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document saved.')]);

        return to_route('projects.knowledge.show', ['project' => $project->slug, 'knowledgeDocument' => $knowledgeDocument->id]);
    }

    private function form(Project $project, ?KnowledgeDocument $document, ?DocType $type = null): Response
    {
        return Inertia::render('projects/knowledge/edit', [
            new ProjectPageProps($project),
            'document' => $document ? KnowledgeDocumentResource::make($document) : null,
            'defaultType' => $type,
            'options' => [
                'docTypes' => DocType::options(),
                'statuses' => DocStatus::options(),
            ],
        ]);
    }
}
