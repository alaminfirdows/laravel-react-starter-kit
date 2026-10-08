# [PRODUCT_NAME] — Project Context

> Working name in code: `founder-os`. Replace `[PRODUCT_NAME]`, `[DOMAIN]` and other bracketed items.
> Last researched: 2026-10-07. Items marked **VERIFY** depend on fast-moving vendor features.

---

## 1. What we are building (one paragraph)

A **Founder Action Planner / Company-Building OS**. A founder logs in, creates a **Project** (their company/product), picks a **Pack** (a curated bundle from our **Catalog**), and receives a structured plan: **Categories → Tasks → Subtasks → Actions**. Each task explains _what_ and _why_ (rich Markdown with images, links, resources) and each action says _how_ it gets done — by Claude (chat, Cowork, Chrome), by our app's own AI, or manually by the founder. Our app is the **source of truth** for plan, state, files and company knowledge. **Claude Desktop is the execution environment**, connected to us through **our remote MCP server** and our **bundled skills** (shipped as a Claude plugin). Every change, by human or agent, is logged.

## 2. Separation of responsibilities

| Layer                                        | Owns                                                                                                    | Never does                                    |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| **App (Laravel + Inertia/React)**            | Catalog, projects, tasks, actions, status, approvals, files, knowledge base, activity log               | Re-implement a general AI agent / chat client |
| **Knowledge service** (same app, own module) | Markdown documents, chunks, embeddings (pgvector), hybrid search, project context snapshot              | Store state about tasks                       |
| **MCP server** (`laravel/mcp`, same app)     | The only door for Claude: read context, read task, update action/task, save knowledge, request approval | Expose raw DB / SQL, bypass policies          |
| **Skills** (in plugin)                       | _HOW_ to perform specialised work (ICP, competitor research, GSC setup…)                                | Hold live data                                |
| **Prompt** (per action)                      | _WHAT_ to do now, for which task                                                                        | Carry full company context (fetched via MCP)  |
| **Claude Desktop / Cowork / Chrome**         | Executes, browses, writes files, calls MCP                                                              | Decide what "done" means (we verify)          |
| **In-app AI** (`laravel/ai`)                 | Small server-side jobs: drafting, summarising, classifying, embedding, checks                           | Long interactive / browser work               |

Rule of thumb: **Skill = how · MCP = data & state · Prompt = what now · App = truth.**

## 3. Research findings that shape the design

1. **"Open in Claude" is a prefill, not auto-run.** Claude Desktop handles `claude://` links: `claude://claude.ai/new?q=…` (chat) and `claude://cowork/new?q=…` (Cowork). The prompt is prefilled for the user to review and send, and `q` is truncated at roughly 14,000 characters. ⇒ The deep-link prompt must be a short **launcher** ("use the [PRODUCT_NAME] connector, load task T-…, follow it"); full context comes via MCP.
2. **Remote MCP = custom connector.** Users add our server in Claude via Settings → Connectors → Add custom connector; Desktop ignores remote servers put in `claude_desktop_config.json`. Custom connectors are on Pro/Max/Team/Enterprise (Free is limited). Claude supports OAuth (with DCR, or a manually supplied client ID/secret) and Streamable HTTP. Callback: `https://claude.ai/api/mcp/auth_callback`. **VERIFY plan limits at launch.**
3. **`laravel/mcp` + Passport gives the full OAuth 2.1 flow** (PKCE, DCR, discovery metadata) via `Mcp::oauthRoutes()` + `auth:api` middleware. Laravel MCP 1.0 adds **searchable tool catalogs** (`ToolSearch`) so rarely-used tools don't bloat Claude's context.
4. **Ship skills + connector as one Claude plugin.** A plugin folder bundles skills and an `.mcp.json` remote connector; users upload the zip (Customize → Plugins → Upload) or add our Git marketplace. One install instead of N skill zips + manual connector URL. **VERIFY exact plugin manifest fields.**
5. **Skill rules:** folder name = `name`; `name` ≤ 64 chars, lowercase/hyphen; claude.ai shows descriptions up to 200 chars; keep `SKILL.md` < 500 lines, put detail in `references/`.
6. **The same skills power our in-app agents.** `laravel/ai` agents implementing `HasSkills` load folders from `resources/skills` — so `resources/skills` is the single source for both the plugin build and server-side agents.
7. **Laravel 13 + pgvector is first-class:** `$table->vector('embedding', dimensions: 1536)->index()` (HNSW, cosine), `AsVector` cast, `whereVectorSimilarTo()`, `SimilaritySearch` tool, embedding caching, test fakes. No separate vector DB.

## 4. Core concepts (glossary)

- **Workspace** — tenant boundary. Phase 1: one personal workspace auto-created per user. Phase 3: multi-member.
- **Project** — one company/product. Holds profile, brand, knowledge, plan.
- **Catalog** — our authored, versioned library: categories, task templates, action templates, prompt templates, resources, skills.
- **Pack** — curated subset of catalog tasks (e.g. "Pre-launch SaaS", "Legal & Privacy EU", "SEO Day-1").
- **Task** — project instance of a catalog task (or custom). Tree via `parent_id` (Task → Subtask, max depth 3).
- **TaskAction** — executable unit inside a task (named `TaskAction` to avoid clashing with Laravel "Actions" classes). Typed (see §6).
- **ActionRun** — one execution attempt of an action (who/what/which channel/output/evidence).
- **Evidence** — proof attached to completion (URL, value, file, screenshot, MCP check).
- **Approval** — human gate before irreversible steps.
- **Knowledge Document** — Markdown doc in the project brain (ICP, positioning, interview notes, SOPs…), chunked + embedded.
- **Decision** — decision-log entry (date, decision, why, alternatives, owner).

## 5. Project profile — recommended fields

Required at creation: `name`, `one_liner` (≤140), `stage`, `business_model`, `primary_market`.

| Group             | Fields                                                                                                                                                                                       |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Identity          | name, slug, one_liner, description_md, logo, website_url, primary_domain                                                                                                                     |
| Business          | business_model (b2b_saas, b2c_app, marketplace, agency, ecommerce, other), industry, stage (idea, validating, building, pre_launch, launched, revenue, scaling), pricing_model, revenue_band |
| Market            | primary_market (country), target_markets[], languages[], target_customer (free text until ICP doc exists), problem_statement, solution_summary                                               |
| Company           | legal_entity_status (none, in_progress, registered), entity_type, jurisdiction, founded_on, team_size, founders[]                                                                            |
| Ops               | timezone, currency, fiscal_year_start                                                                                                                                                        |
| Goals             | goals[] (title, metric, target, due) — e.g. "First 10 paying customers by Q1"                                                                                                                |
| Brand (own table) | colors (hex + role), fonts, voice_md, tone keywords, logo variants, social handles                                                                                                           |
| Tech              | stack (json), repos[], hosting                                                                                                                                                               |

Strategy content (ICP, personas, positioning, messaging, pricing…) is **not** columns — it lives as typed Knowledge Documents, so it can be long, versioned, embedded and edited by Claude.

## 6. Action types & executors

`type` (what) × `executor` (who runs it):

| type        | Meaning                                           | Typical executor        | Done when                                   |
| ----------- | ------------------------------------------------- | ----------------------- | ------------------------------------------- |
| `ai`        | Generate/analyse content                          | claude_desktop / app_ai | Output saved to knowledge or task           |
| `research`  | Multi-source research                             | claude_desktop          | Research doc saved                          |
| `browser`   | Operate a website (GSC, registrar, Stripe…)       | claude_chrome           | Evidence (IDs, screenshot, URL)             |
| `document`  | Produce a doc (PRD, policy draft)                 | claude_desktop / app_ai | Document created & linked                   |
| `file`      | Produce a file (brand-guidelines.md, sitemap)     | claude_desktop (Cowork) | File uploaded / linked                      |
| `mcp`       | State change in our app only                      | claude_desktop          | Tool call recorded                          |
| `check`     | Verify a condition (HTTPS, SPF/DKIM, sitemap 200) | app_system / claude     | Check passes (machine-verifiable preferred) |
| `input`     | Ask founder for data (form schema)                | user                    | Form submitted                              |
| `approval`  | Founder must approve                              | user                    | Approval granted                            |
| `manual`    | Human-only (sign agreement, open bank account)    | user                    | User marks done (+ optional evidence)       |
| `wait`      | External wait (registration, DNS)                 | system                  | Date/condition reached or user confirms     |
| `scheduled` | Recurring (quarterly competitor review)           | system                  | Next occurrence generated                   |

`executor` ∈ `claude_desktop | claude_chrome | app_ai | app_system | user`.

## 7. Key workflows

### 7.1 Onboarding & project creation

1. Sign up → personal workspace created.
2. Create project (wizard: identity → business → market → goals). Optional: paste website URL → in-app AI drafts description/industry (user confirms).
3. Choose pack(s) (recommended by stage + business model) → **snapshot** catalog tasks/actions into project (`catalog_version` stored).
4. "Connect Claude" checklist: install plugin zip → add/connect connector (OAuth) → run "test connection" prompt (calls `whoami` + `get_project_context`).

### 7.2 Executing a task

```
Task page → read description, resources, required skills
   ├─ [Copy full prompt]      → self-contained prompt (context inlined, ≤ ~14k chars)
   ├─ [Open in Claude]        → claude://claude.ai/new?q=<launcher>  (or cowork/new for file/browser work)
   ├─ [Run in app]            → app_ai agent (laravel/ai), queued, streamed to UI
   └─ [Mark done manually]    → optional evidence form
Claude (with plugin skill + connector):
   get_task → start_action → (work: AI / Chrome / files) → save_knowledge / attach_evidence
   → complete_action(evidence) | request_approval | report_blocker
App: validates completion criteria → updates action → rolls up task status → activity log → UI refresh (polling/Reverb)
```

### 7.3 Status model

- **TaskAction:** `pending → ready → running → (awaiting_input | awaiting_approval) → done | failed | skipped`
- **Task:** `locked` (hard deps not done) · `todo` · `in_progress` · `blocked` · `awaiting_approval` · `done` · `skipped`. Task becomes `done` automatically when all _required_ actions are `done`/`skipped`; parent progress = weighted children.
- **Verification:** each completion has `verification` = `self_reported | evidence_attached | verified` (machine check passed). Dashboard shows the difference.

### 7.4 Human-in-the-loop

Claude calls `request_approval(action, summary, payload)` → action `awaiting_approval` → founder approves/rejects in app (notification) → Claude polls `get_task` or the user says "continue". Catalog flags `requires_approval` for legal filings, payments, outbound comms, DNS changes, deletions.

### 7.5 Knowledge ingestion

Doc created/updated (user, Claude via MCP, upload) → version row → queued job: split by headings (~500–800 tokens, overlap ~80) → embeddings → `knowledge_chunks`. Search = hybrid (pgvector cosine + Postgres full-text) merged by reciprocal rank fusion, always scoped by `project_id`.

### 7.6 Catalog evolution

Catalog items are versioned. Projects keep their snapshot; when a newer catalog version exists the task shows "Update available" with a diff; user accepts per task. Custom edits by the founder are never overwritten silently.

## 8. Prompt strategy (two forms per action)

- **Launcher prompt** (deep link): ~5–15 lines, no company data, references `project_id` + `task_id` + `action_id`, names the skill, tells Claude to fetch context via MCP and to report back via MCP. Small, stable, safe to prefill.
- **Full prompt** (copy button / users without connector): template rendered server-side with `{{project.*}}`, `{{brand.*}}`, `{{docs.icp}}`… Keeps working without MCP; ends with "paste results back into [PRODUCT_NAME]".
- Every prompt template ends with the **completion protocol**: verify criteria → save outputs → attach evidence → update status → list follow-ups. (Also enforced by the `founder-os-task-runner` skill.)

## 9. Phased roadmap

| Phase                | Scope                                                                                                                                             |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| **P0 Foundation**    | Auth, personal workspace, project CRUD, catalog seed (from checklist doc), packs, task tree UI, Markdown content, manual completion, activity log |
| **P1 Claude bridge** | Passport + MCP server (core tools), launcher/full prompts, deep links, plugin build + download, connection test, ActionRuns, evidence             |
| **P2 Knowledge**     | Knowledge docs, versioning, chunking + pgvector, `search_knowledge`, project context snapshot, decision log                                       |
| **P3 In-app AI**     | `laravel/ai` agents for app_ai actions, machine checks (DNS/SPF/HTTPS/sitemap), approvals UI, notifications                                       |
| **P4 Workspaces**    | Members, roles, assignment, comments, mentions, per-member MCP tokens, audit views, real-time (Reverb)                                            |
| **P5 Marketplace**   | Catalog authoring UI, community packs, affiliate resources, analytics on task completion                                                          |

## 10. Non-goals (for now)

Own chat UI competing with Claude; auto-submitting prompts; storing third-party credentials for Claude to use (token passthrough is forbidden by MCP guidance); legal advice (policy/legal tasks produce drafts flagged for counsel).

## 11. Open questions — `[TO DECIDE]`

- [Final product name / domain]
- [Embedding provider + dimensions] (default proposal: 1536)
- [Which Claude plans you support officially — Pro+ only for connector path?]
- [Is the earlier Tauri desktop client dropped, or a later shell around this web app?]
- [Billing model: per workspace / per project / seats]
- [Hosting target: Laravel Cloud / Forge / Docker]
