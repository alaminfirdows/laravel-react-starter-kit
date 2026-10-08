# P1 Claude Bridge — Outline

> **For agentic workers:** OUTLINE. Before execution, re-run superpowers:writing-plans on this file to expand each task into full TDD steps against the code that exists after P0.

**Goal:** Claude (Desktop/Chat/Cowork) works on project tasks through MCP: founder clicks "Open in Claude" → deep link with a launcher prompt → Claude reads context, does the step, saves output and evidence, marks the action done. Plus a downloadable plugin with skills.

**Architecture:** `laravel/passport` (OAuth 2.1 + DCR) guards `routes/ai.php`. `FounderServer` (`laravel/mcp`) exposes small tools that call the same `app/Domain` Actions as the web UI (rule 1). Every tool call writes `action_runs` + `activity_log` with `actor_type=agent`, `channel=mcp`, `client_name` from `oauth_clients.name`.

**Spec:** `docs/MCP_AND_SKILLS.md` (tool list, prompts, skills), `docs/DATA_MODEL.md` §C (`action_runs`, `evidence`, `approvals`), §F rules 2–5, `docs/PROJECT_CONTEXT.md` §8.

## Global Constraints

- MCP may never: delete projects/tasks, change catalog, change members, approve its own approval (§F.5).
- Launcher prompt: IDs + skill names only, no company data. Deep link URL ≤ 8 000 chars; fall back to copy when over.
- Tools: typed schemas, descriptive param names, compact Markdown output, paginate lists, read-only annotation on readers.
- Throttle MCP routes (`throttle:mcp`, 120/min per token).

## Review Focus

1. Token of user A calls `get_task` with a task ULID of workspace B → "not found", no data.
2. Viewer-role token calls `complete_action` → refused.
3. `complete_action` with an `evidence` criterion and no evidence → refused with the missing criterion named.
4. Agent tries to approve an approval it requested → refused.
5. Deep link over the length cap → UI falls back to copy prompt.

## Tasks

| #   | Task                                    | Files / classes                                                                                                                                                                                                                                    | Tests                                                                                                   |
| --- | --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| 1   | Skills + resources catalog tables       | migration `skills`, `catalog_task_skill`, `resources`, `catalog_task_resource`; models `Catalog\Models\{Skill,Resource}`; `ImportCatalog` reads `skills:` from `resources/skills/*/SKILL.md` frontmatter and `resources.yaml`                      | `ImportCatalogTest` (skills + resources upsert)                                                         |
| 2   | Action runs, evidence, approvals schema | migration `action_runs`, `evidence`, `approvals`; FK `task_actions.last_run_id`; models `Task\Models\{ActionRun,Evidence,Approval}`; enums `RunChannel`, `RunStatus`, `EvidenceKind`, `ApprovalStatus`                                             | `ActionRunModelTest`                                                                                    |
| 3   | Action lifecycle Actions                | `Task\Actions\{StartAction,CompleteAction,SkipAction,AttachEvidence,EvaluateCriteria,RequestApproval,DecideApproval}`; `CompleteAction` replaces direct action close in `MarkTaskDone` (leaf done = all required actions closed, §F.3)             | `ActionLifecycleTest`: criteria evidence/check, approval gate §F.4, auto-done task, rollup              |
| 4   | Rollup as debounced job                 | `Task\Jobs\RollupProgressJob` (unique per project, 2 s), `projects.progress_pct` cached column; `RollupTaskStatus` stays the logic                                                                                                                 | `RollupProgressJobTest` (`Queue::fake`, uniqueness)                                                     |
| 5   | Prompt rendering v2                     | `Prompt\Actions\{RenderLauncherPrompt,BuildDeepLink}`; `RenderFullPrompt` gains skill list + token budget via `Prompt\Support\TokenBudget`                                                                                                         | `RenderLauncherPromptTest` (no project data leaks), `BuildDeepLinkTest` (encoding, cap, chat vs cowork) |
| 6   | Passport + MCP auth                     | `composer require laravel/passport laravel/mcp`; `passport:install`; DCR enabled; `routes/ai.php` with `auth:api`; `Mcp\Support\McpActor` (token → User + client name → `Actor::agent`) + workspace resolution from token scope `workspace:<ulid>` | `McpAuthTest` (no token 401, scope binds workspace)                                                     |
| 7   | `FounderServer` read tools              | `Mcp/Servers/FounderServer.php`; tools `ListProjects`, `GetProjectContext`, `ListTasks` (filters status/category, paginated), `GetTask`, `GetAction`                                                                                               | per-tool tests with `FounderServer::actingAs(...)->tool(...)`; isolation test (Review Focus 1)          |
| 8   | `FounderServer` write tools             | `StartAction`, `CompleteAction`, `AttachEvidence`, `SaveOutput` (stores `output_md` on run), `RequestApproval`; each validates → authorizes (`TaskPolicy::update`) → Domain Action → activity                                                      | `McpWriteToolsTest` (Review Focus 2–4)                                                                  |
| 9   | MCP prompts + resources                 | `Mcp/Prompts/WorkOnTask`, `Mcp/Resources/ProjectContext` (`founder://projects/{id}/context`)                                                                                                                                                       | `McpPromptsTest`                                                                                        |
| 10  | Task page: Open in Claude + runs        | enable "Open in Claude" (`BuildDeepLink`), action status badges, run history list, evidence list, approval banner; Inertia polling (`usePoll` 10 s while a run is `started`)                                                                       | `TaskHttpTest` props; manual smoke                                                                      |
| 11  | Connect Claude page                     | `settings/connect-claude` page: connector URL, steps, active tokens list + revoke                                                                                                                                                                  | `ConnectClaudeTest`                                                                                     |
| 12  | Plugin build                            | `php artisan plugin:build` → `plugin/.claude-plugin/plugin.json`, `.mcp.json`, copies `resources/skills/*`                                                                                                                                         | `PluginBuildCommandTest` (snapshot of manifest)                                                         |
| 13  | Docs + gate                             | update `docs/MCP_AND_SKILLS.md` with final tool names; `composer ci:check`; MCP Inspector smoke (`php artisan mcp:inspector founder`, VERIFY name)                                                                                                 | —                                                                                                       |
