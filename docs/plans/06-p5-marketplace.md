# P5 Catalog Authoring & Marketplace — Outline

> **For agentic workers:** OUTLINE. Before execution, re-run superpowers:writing-plans on this file to expand each task into full TDD steps against the code that exists after P4.

**Goal:** Catalog content is edited in an admin UI (not only YAML), projects get notified of catalog updates and can upgrade with a diff, community packs can be published, and we see how tasks perform.

**Architecture:** Admin area (`/admin`, `is_admin` gate) edits catalog rows through `Catalog\Actions\*` that bump `version` + `content_hash` (same rules as `ImportCatalog`). `DiffCatalogVersion` compares a project task snapshot with the current catalog row; `UpgradeTaskFromCatalog` applies it field by field. Community packs are `packs` with `owner_workspace_id` and `visibility`.

**Spec:** `docs/CLAUDE.md` rule 7, `docs/DATA_MODEL.md` §B, `tasks.has_catalog_update`.

## Global Constraints

- Catalog stays immutable at runtime for founders; only admins and pack owners write.
- Upgrades never overwrite founder-edited fields without explicit choice.
- Community packs reviewed before `published`.

## Review Focus

1. Founder edited a task body; catalog changes the same body → diff shows conflict, default keeps founder version.
2. Catalog task removed from catalog → project task stays (custom), flag cleared.
3. YAML import after admin edits → admin edits not silently lost (import refuses or versions).
4. Private community pack never listed for other workspaces.
5. Analytics queries scoped and aggregated, no cross-workspace row data.

## Tasks

| # | Task | Files / classes | Tests |
| --- | --- | --- | --- |
| 1 | Admin gate + layout | `users.is_admin`, `Gate::define('admin')`, `layouts/admin-layout.tsx` | `AdminAccessTest` |
| 2 | Catalog editing Actions | `Catalog\Actions\{SaveCatalogTask,SaveCatalogAction,SavePromptTemplate,SavePack,PublishCatalogTask}` sharing versioning with `ImportCatalog` (extract `Catalog\Support\Versioning`) | `CatalogEditingTest` |
| 3 | Admin UI | pages `admin/catalog/*` (tree editor, Tiptap body, actions, prompts, packs) | `AdminCatalogHttpTest` |
| 4 | YAML ↔ DB sync | `catalog:export` command; `catalog:import --check` refuses when DB has newer admin edits | `CatalogSyncTest` (Review Focus 3) |
| 5 | Update detection | `Catalog\Jobs\FlagCatalogUpdates` sets `tasks.has_catalog_update` after publish | `FlagCatalogUpdatesTest` (Review Focus 2) |
| 6 | Diff + upgrade | `Catalog\Actions\DiffCatalogVersion`, `Task\Actions\UpgradeTaskFromCatalog`; task page "Update available" dialog with per-field diff | `UpgradeTaskTest` (Review Focus 1) |
| 7 | Extra packs | "Add pack" on project (non-default packs for phase), uses `ApplyPack` | `AddPackHttpTest` |
| 8 | Community packs | `packs.owner_workspace_id`, `visibility (private|public)`, `review_status`; submit + review flow | `CommunityPackTest` (Review Focus 4) |
| 9 | Analytics | `Analytics\Queries\{TaskCompletionStats,DropOffByTask}` from `activity_log`; admin dashboard | `AnalyticsTest` (Review Focus 5) |
| 10 | Docs + gate | `composer ci:check` | — |
