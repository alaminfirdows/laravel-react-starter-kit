<?php

namespace App\Domain\Project\Actions;

use App\Domain\Knowledge\Enums\DocStatus;
use App\Domain\Knowledge\Enums\DocType;
use App\Domain\Knowledge\Models\Decision;
use App\Domain\Knowledge\Models\KnowledgeDocument;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Support\ProjectMarkdown;
use App\Domain\Prompt\Support\TokenBudget;
use App\Domain\Workspace\Scopes\WorkspaceScope;
use Illuminate\Support\Str;

/**
 * Caches the project brief for prompts and MCP on `projects.context_snapshot_md`:
 * profile, approved singleton documents (summaries + IDs) and recent decisions.
 */
class BuildContextSnapshot
{
    public const int MAX_CHARS = 6000;

    public const int SUMMARY_CHARS = 600;

    public const int DECISIONS = 10;

    public function __construct(protected ProjectMarkdown $markdown) {}

    public function handle(Project $project): string
    {
        $snapshot = (new TokenBudget(intdiv(self::MAX_CHARS, TokenBudget::CHARS_PER_TOKEN)))->trim(implode("\n\n", array_filter([
            $this->markdown->context($project, ['profile', 'market', 'goals', 'brand']),
            $this->documents($project),
            $this->decisions($project),
        ])));

        $project->forceFill(['context_snapshot_md' => $snapshot])->saveQuietly();

        return $snapshot;
    }

    /**
     * The cached snapshot, built on first use.
     */
    public function current(Project $project): string
    {
        return $project->context_snapshot_md ?? $this->handle($project);
    }

    protected function documents(Project $project): ?string
    {
        $documents = $project->knowledgeDocuments()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('status', DocStatus::Approved)
            ->whereIn('doc_type', DocType::singletons())
            ->get(['id', 'doc_type', 'title', 'body_md'])
            ->sortBy(fn (KnowledgeDocument $document): int|false => array_search($document->doc_type, DocType::singletons(), true));

        if ($documents->isEmpty()) {
            return null;
        }

        return "## Approved documents\n".$documents
            ->map(fn (KnowledgeDocument $document): string => "### {$document->doc_type->label()}: {$document->title} (`{$document->id}`)\n".$this->summary($document->body_md))
            ->implode("\n\n");
    }

    protected function decisions(Project $project): ?string
    {
        $decisions = $project->decisions()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->latest('decided_on')
            ->latest()
            ->limit(self::DECISIONS)
            ->get(['id', 'title', 'decided_on']);

        if ($decisions->isEmpty()) {
            return null;
        }

        return "## Recent decisions\n".$decisions
            ->map(fn (Decision $decision): string => "- {$decision->decided_on->toDateString()} {$decision->title} (`{$decision->id}`)")
            ->implode("\n");
    }

    protected function summary(string $markdown): string
    {
        $text = trim((string) preg_replace(['/^#{1,6}\s.*$/m', '/\n{2,}/'], ['', "\n"], $markdown));

        return Str::limit($text, self::SUMMARY_CHARS);
    }
}
