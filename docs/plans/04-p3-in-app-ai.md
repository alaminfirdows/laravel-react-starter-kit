# P3 In-App AI — Outline

> **For agentic workers:** OUTLINE. Before execution, re-run superpowers:writing-plans on this file to expand each task into full TDD steps against the code that exists after P2.

**Goal:** Actions with `executor=app_ai` or `app_system` run inside the app: AI agents draft documents and reviews, machine checks verify things (HTTPS, DNS, sitemap), approvals have a proper UI, and users get notified.

**Architecture:** `laravel/ai` agents in `app/Ai/Agents` implement `HasSkills` and read the same `resources/skills/*/SKILL.md` as the plugin. `RunAppAiActionJob` creates an `ActionRun`, calls the agent, stores `output_md` + `usage`, then calls `CompleteAction` or `RequestApproval`. Checks implement `App\Checks\Check` and run via `RunCheckJob`, writing `evidence(kind=check_result)`.

**Spec:** `docs/MCP_AND_SKILLS.md` (skills), `docs/DATA_MODEL.md` §C `action_runs.usage`, `approvals`, §F.2, §F.4, `docs/CLAUDE.md` rule 9.

## Global Constraints

- Any AI call > 5 s is queued; interactive output streams to UI.
- Usage (tokens, model) stored on `action_runs.usage`.
- Tests use `Agent::fake()`; no network. Checks use `Http::fake()` / fake DNS resolver.
- Default model: `claude-sonnet-5-5`; heavy drafting: `claude-opus-5-5` (config `ai.models.*`).

## Review Focus

1. Agent failure / timeout → run `failed`, action back to `ready`, user sees error, retry works.
2. Agent output for a `requires_approval` action → approval pending, action not done.
3. Check against an unreachable domain → `check_result` failed with reason, no crash.
4. Two clicks on "Run with AI" → one run (unique job / lock).
5. Per-workspace AI budget exceeded → refused before calling the provider.

## Tasks

| # | Task | Files / classes | Tests |
| --- | --- | --- | --- |
| 1 | AI setup | `composer require laravel/ai`; publish config; `config/ai.php` models; `.env.example` keys | `AiConfigTest` |
| 2 | Skill loader | `Ai\Support\SkillRepository` (parse SKILL.md frontmatter + body) | `SkillRepositoryTest` |
| 3 | Agents | `Ai\Agents\{DraftDocumentAgent,ReviewAgent,ResearchSummaryAgent}` with structured output (`output_md`, `outputs[]`) | `DraftDocumentAgentTest` (`Agent::fake`) |
| 4 | Run job | `Ai\Jobs\RunAppAiActionJob` (ShouldBeUnique per action, timeout, failed handler) + `Task\Actions\RunActionInApp` entry point | `RunAppAiActionJobTest` (Review Focus 1, 2, 4) |
| 5 | Budget | `workspaces.settings.ai_budget`, `Ai\Support\UsageMeter` (monthly sum of `action_runs.usage`) | `UsageMeterTest` (Review Focus 5) |
| 6 | Checks | `Checks\Check` interface, `HttpsCheck`, `DnsSpfCheck`, `SitemapCheck`, `RobotsCheck`; `Checks\Jobs\RunCheckJob`; registry by `config.check` key | one test per check with `Http::fake` (Review Focus 3) |
| 7 | Scheduled + wait actions | `Task\Console\ProcessScheduledActions` (rrule, wait rules) in scheduler | `ProcessScheduledActionsTest` |
| 8 | Approvals UI | `projects/approvals/index` page, approve/reject with note (`DecideApproval`), badge count in sidebar | `ApprovalHttpTest` |
| 9 | Notifications | `ApprovalRequested`, `ActionFailed`, `RunFinished` (database + mail); bell menu component | `NotificationsTest` |
| 10 | Task page | "Run with AI" button for `app_ai` actions, streaming output panel, run usage, check results | `TaskHttpTest` additions; manual smoke |
| 11 | Docs + gate | `composer ci:check` | — |
