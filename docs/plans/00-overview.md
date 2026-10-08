# Founder OS — Implementation Plan Overview

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement each phase plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Founder OS (P0 → P5): workspace → project (phase-driven wizard) → catalog-snapshot task tree → task page with progress, subtasks and Claude prompts, then the Claude bridge, knowledge, in-app AI, team features and the marketplace.

**Spec:** `docs/PROJECT_CONTEXT.md`, `docs/DATA_MODEL.md`, `docs/MCP_AND_SKILLS.md`, `docs/CLAUDE.md`. Read them first. This plan wins only where the "Decisions" table below says so.

## Plan files

| File | Phase | Detail level |
| --- | --- | --- |
| `01-p0-foundation.md` | P0 Foundation: Postgres, catalog, projects, wizard, task tree UI, manual completion, copy prompt, activity log | Full TDD steps |
| `02-p1-claude-bridge.md` | P1: Passport, MCP server, launcher/full prompts, deep links, ActionRuns, evidence, plugin build | Task outline — expand before start |
| `03-p2-knowledge.md` | P2: knowledge docs, versions, pgvector chunks, hybrid search, context snapshot, decisions | Task outline — expand before start |
| `04-p3-in-app-ai.md` | P3: `laravel/ai` agents, machine checks, approvals UI, notifications | Task outline — expand before start |
| `05-p4-workspaces.md` | P4: assignment, comments, mentions, per-member MCP tokens, audit views, Reverb | Task outline — expand before start |
| `06-p5-marketplace.md` | P5: catalog authoring UI, catalog upgrades + diff, community packs, analytics | Task outline — expand before start |

Before starting phase N ≥ 1: re-run `superpowers:writing-plans` on that file to turn the outline into full TDD steps against the code that exists then. Outlines name tables, classes and tests so the expansion is mechanical.

## Decisions (agreed 2026-10-08, override the spec where they differ)

| Topic | Decision |
| --- | --- |
| Tenancy | Reuse `App\Domain\Workspace` as is. UI label stays "Workspace". Signup already creates a personal workspace (`CreateNewUser` → `CreateWorkspace::personal`). |
| Code layout | Module per domain, same as Workspace: `app/Domain/<Module>/{Models,Enums,Actions,Queries,Data,Http/Controllers,Http/Requests,Policies,Console,Providers}`. Factories stay in `database/factories`. `docs/CLAUDE.md` folder layout updated in P0 Task 19. |
| Phase | New `ProjectPhase` enum: `planning`, `developing`, `selling`. Chosen first in "Add project". Each phase maps to one default Pack (`packs.audience.phase` + `is_default`). Spec `stage` stays a separate profile field. Spec `catalog_categories.phase` stays (renamed enum `CatalogPhase`). |
| Wizard | Step 0 (phase + name) creates a **draft** project and applies the default pack at once. Steps identity → business → market → goals edit the draft. Finishing the last step activates it (requires `name`, `one_liner`, `stage`, `business_model`, `primary_market`). |
| Catalog source | YAML in `database/seeders/catalog/*.yaml`, imported by `ImportCatalog` (`php artisan catalog:import`). P0 ships sample content; real content supplied by the founder later. |
| Completion | Spec model. Leaf task holds TaskActions. User checks a leaf → `MarkTaskDone` closes its open actions (`verification=self_reported`) → `RollupTaskStatus` updates ancestors. Parent progress = mean of leaf `progress_pct`. |
| Task UI | Inside a project the app sidebar is replaced by a project sidebar holding the task tree (grouped by category). Main area = task detail. Progress bar top-right. Subtask page = same layout + breadcrumb + parent task card on top. |
| Database | PostgreSQL 16+ now, for dev and tests. Enums stored as `string` + CHECK constraint (`App\Support\Database\EnumCheck`). |
| Editor | Tiptap v3 + `@tiptap/markdown` (Markdown in/out). Render with `react-markdown` + `remark-gfm` + `rehype-sanitize`. |
| Deferred from P0 | `skills`, `resources`, `catalog_task_skill`, `catalog_task_resource` (P1), deep links / ActionRuns (P1), Reverb (P4). |

## Global conventions (every phase)

- ULID primary keys on everything exposed to UI/MCP (`HasUlids`). Catalog tables use bigint.
- Tenant models use `App\Domain\Workspace\Concerns\BelongsToWorkspace` (fail-closed global scope). Cross-workspace queries use `withoutWorkspaceScope()` / `forWorkspace()` on purpose only.
- Status fields change only inside `app/Domain/*/Actions` classes, via `forceFill()`. Status columns are never in `#[Fillable]`.
- Every mutation calls `App\Domain\Activity\ActivityRecorder::record()`.
- Controllers are thin: authorize → FormRequest → Action → `Inertia::render()` / redirect + `Inertia::flash('toast', …)`.
- Model attributes use PHP 8 attributes as in `Workspace`: `#[Fillable]`, `#[UseFactory]`, `#[UsePolicy]`.
- Frontend: Inertia v3 `<Form>` + Wayfinder (`@/actions/...`, `@/routes/...`), shadcn components in `resources/js/components/ui` (generated, do not hand-edit).
- Gate per PR: `composer ci:check` green (Pest + Pint + Larastan + `vp check` + `tsc`).
