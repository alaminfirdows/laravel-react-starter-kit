# CLAUDE.md — Development Instructions

Read `PROJECT_CONTEXT.md` first, then `docs/DATA_MODEL.md` and `docs/MCP_AND_SKILLS.md`. These are the spec; if code and spec disagree, ask before changing the spec.

## Stack

- **Backend:** Laravel 13 (PHP ≥ 8.3), PostgreSQL 16+ with `pgvector`, Redis (queue/cache), Laravel Horizon.
- **Frontend:** Laravel React starter kit — Inertia + React + TypeScript + Tailwind + shadcn/ui. Wayfinder/Ziggy for typed routes (whichever the starter kit ships).
- **AI in app:** `laravel/ai` (agents, embeddings, reranking, skills, fakes).
- **MCP:** `laravel/mcp` 1.x + `laravel/passport` (OAuth 2.1 for Claude custom connector).
- **Auth (web):** starter-kit auth (Fortify) + Passport for MCP only.
- **Rich text:** canonical **Markdown** in DB. Editor: Tiptap-based shadcn-compatible editor with Markdown import/export (VERIFY package at implementation; Milkdown is the fallback). Render with a sanitizing Markdown renderer (server: league/commonmark with safe mode; client: react-markdown + rehype-sanitize).
- **Testing:** Pest, Laravel MCP testing helpers / MCP Inspector, `Agent::fake()`, `Embeddings::fake()`.
- **Quality:** Pint, Larastan (level 6+), ESLint + Prettier, TypeScript strict.

## Setup commands

```bash
composer require laravel/ai laravel/mcp laravel/passport laravel/horizon
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan passport:install && php artisan passport:keys
php artisan migrate --seed            # seeds catalog from database/seeders/catalog/*.yaml
npm i && npm run dev
php artisan mcp:inspector founder     # VERIFY command name in installed laravel/mcp version
```

`.env` keys: `ANTHROPIC_API_KEY`, `[EMBEDDINGS_PROVIDER]_API_KEY`, `AI_EMBEDDING_DIMENSIONS=1536`, `APP_URL=https://[DOMAIN]`.

## Folder layout

```
app/
  Enums/                 TaskStatus, ActionType, Executor, ActionStatus, DocType, Phase, Stage …
  Models/                Workspace, Project, ProjectBrand, Task, TaskAction, ActionRun, Evidence,
                         Approval, Decision, KnowledgeDocument, KnowledgeChunk, Media, Catalog\*
  Domain/                business logic, one class per use case (verb-first):
    Projects/            CreateProject, ApplyPack, BuildContextSnapshot
    Tasks/               StartAction, CompleteAction, EvaluateCriteria, RollupTaskStatus, RequestApproval
    Knowledge/           SaveDocument, ChunkDocument, EmbedChunks, HybridSearch
    Prompts/             RenderLauncherPrompt, RenderFullPrompt, BuildDeepLink
    Catalog/             ImportCatalog, DiffCatalogVersion
  Activity/ActivityRecorder.php
  Policies/
  Http/Controllers/      thin; call Domain classes; return Inertia::render()
  Mcp/Servers/FounderServer.php
  Mcp/Tools/             one class per tool; validate → authorize → call Domain → format
  Mcp/Resources/  Mcp/Prompts/
  Ai/Agents/             app_ai executors (DraftDocumentAgent, ReviewAgent …) — implement HasSkills
  Ai/Tools/
  Jobs/                  EmbedDocumentJob, RunAppAiActionJob, RunCheckJob, RollupProgressJob
  Checks/                machine checks: HttpsCheck, DnsSpfCheck, SitemapCheck … (implement Check)
resources/
  js/pages/              Inertia pages: dashboard, projects/*, tasks/show, knowledge/*, connect-claude
  js/components/ui/      shadcn (generated, do not hand-edit)
  js/components/app/     TaskTree, ActionCard, PromptButtons, EvidenceForm, MarkdownEditor …
  skills/<skill-name>/SKILL.md   ← single source for plugin + in-app agents
database/seeders/catalog/ YAML catalog (categories, tasks, actions, prompts, packs)
plugin/                  build output scaffolding (.claude-plugin/plugin.json, .mcp.json)
routes/web.php  routes/ai.php (MCP)
```

## Rules

1. **Domain classes own logic.** Controllers, MCP tools, jobs and agents all call the same `app/Domain` classes — never duplicate status logic.
2. **Status transitions only via Domain** (`StartAction`, `CompleteAction`, `RollupTaskStatus`). No `$task->update(['status'=>...])` elsewhere. Enforce derived rules in DATA_MODEL §F with tests.
3. **Every mutation records activity** through `ActivityRecorder` with actor (user | agent+client_name | system) and channel (web | mcp | queue).
4. **Scope everything by workspace/project.** Policies on every controller action and MCP tool. Use route model binding with scoping; never `Model::find($idFromModel)` without a workspace constraint.
5. **ULIDs** for exposed models (`HasUlids`). Never expose bigint IDs to UI/MCP.
6. **Markdown is canonical.** Store `*_md`; editor JSON optional. Images: upload → `media` → reference `media://<ulid>`.
7. **Catalog is immutable at runtime.** Applying a pack snapshots catalog rows into `tasks`/`task_actions`. Catalog edits go through YAML seed or (later) admin UI with version bump.
8. **Prompts:** launcher prompts contain IDs + skill names only, never company data; full prompts render with a token budget. Deep links via `BuildDeepLink` (URL-encode, cap length, choose `claude.ai/new` vs `cowork/new`).
9. **AI calls go through `laravel/ai` agents**, queued (`->queue()` / jobs) for anything > 5 s; stream to UI when interactive. Store usage on `action_runs.usage`.
10. **Vectors:** `vector(1536)` + `->index()` (HNSW cosine), `AsVector` cast, `whereVectorSimilarTo`. Hybrid search = vector + `tsvector`, merged with reciprocal rank fusion. Re-embed on document version change only.
11. **MCP tools:** small, typed schemas; descriptive parameter names (tool search scores them); return compact Markdown; paginate; annotate read-only tools; hide with `shouldRegister`.
12. **Security:** no secrets in prompts/skills; sanitize Markdown on render; throttle MCP; no token passthrough.
13. **Frontend:** shadcn components, server-driven pages via Inertia props, optimistic UI only for checkbox-style status; use Inertia partial reloads/polling (Reverb in P4) to reflect MCP-made changes.
14. **Tests required** for: status rules, criteria evaluation, pack application, MCP tool auth (user A cannot touch B's project), prompt rendering, hybrid search ranking (with `Embeddings::fake()`).

## Definition of done (per PR)

Migrations + factories + Pest tests green · Larastan/Pint/ESLint clean · Activity recorded for new mutations · Policy coverage · Docs updated if spec changed.

## Build order (P0 → P2)

1. Enums, migrations, models, factories (A–C of DATA_MODEL).
2. Catalog YAML importer + seed from founder checklist; packs.
3. Project wizard, pack application, task tree UI, task page (Markdown render, resources, manual completion).
4. ActivityRecorder + activity feed.
5. Passport + `FounderServer` hot tools + tests + Inspector.
6. Prompt rendering, deep links, Connect-Claude page, plugin build command.
7. Knowledge docs, versions, chunk/embed jobs, `search_knowledge`, context snapshot.
