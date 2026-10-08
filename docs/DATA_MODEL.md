# Data Model (PostgreSQL 16+ with pgvector)

Conventions

- Primary keys: `ulid` for everything exposed to UI/MCP (`projects`, `tasks`, `task_actions`, `action_runs`, `knowledge_documents`, `approvals`); `bigint` for internal catalog/pivot tables is fine.
- Every project-scoped table carries `project_id` (and `workspace_id` where queried cross-project) → cheap scoping in policies and MCP tools.
- Enums: PHP backed enums + Postgres `text` with CHECK constraint (easier migrations than native PG enums).
- Rich content: `*_md` (canonical Markdown) + optional `*_doc` jsonb (editor JSON, for lossless re-edit). MCP/AI always read `*_md`.
- `meta jsonb` on main tables for non-queried extras. Soft deletes on projects, tasks, documents.
- Timestamps everywhere; `completed_by_type/_id` polymorphic actor (User | McpClient | System).

---

## A. Identity & tenancy

**users** — standard + `timezone`, `locale`, `onboarded_at`.

**workspaces** — `id ulid`, `name`, `slug`, `owner_id`, `type (personal|team)`, `plan`, `settings jsonb`.

**workspace_members** — `workspace_id`, `user_id`, `role (owner|admin|member|viewer)`, `invited_by`, `joined_at`. Unique (workspace_id, user_id).
_Phase 1:_ only the owner row exists; code still checks membership → zero refactor later.

**workspace_invitations** _(P4)_ — email, role, token, expires_at.

Passport tables (`oauth_*`) — MCP clients (Claude registers via DCR); we read `oauth_clients.name` to label agent activity.

---

## B. Catalog (authored by us, versioned, read-only for founders)

**catalog_categories** — `id`, `key` (e.g. `company-foundation`), `name`, `description_md`, `phase (pre_planning|research|foundation|product|launch|growth|operations)`, `icon`, `sort_order`.

**catalog_tasks**

| column                                    | notes                                                                            |
| ----------------------------------------- | -------------------------------------------------------------------------------- |
| id, key                                   | `key` stable slug: `foundation.domain.secure-variations`                         |
| category_id, parent_id                    | template tree                                                                    |
| title, summary (≤280), body_md, body_doc  |                                                                                  |
| applicability jsonb                       | `{stages:[...], business_models:[...], markets:[...]}` for recommendation        |
| priority_default, est_minutes, difficulty |                                                                                  |
| is_optional                               |                                                                                  |
| completion_criteria jsonb                 | `[{key, label, kind: manual                                                      | evidence                | check, check_ref?}]` |
| expected_outputs jsonb                    | `[{kind: document                                                                | file                    | value                | url, doc_type?, label}]` |
| content_hash                              | sha256 of authored fields; `catalog:import` bumps `version` only when it changes |
| version, status (draft                    | published                                                                        | archived), published_at |                      |

**catalog_task_dependencies** — `task_id`, `depends_on_id`, `kind (hard|soft)`.

**catalog_actions** — `id`, `catalog_task_id`, `key`, `title`, `type` (ai|research|browser|document|file|mcp|check|input|approval|manual|wait|scheduled), `executor` (claude_desktop|claude_chrome|app_ai|app_system|user), `instructions_md`, `prompt_template_id?`, `config jsonb` (input form schema, check params, wait rule, schedule rrule, deep-link target chat|cowork), `is_required`, `requires_approval`, `sort_order`, `version`.

**prompt_templates** — `id`, `key`, `title`, `launcher_md`, `full_md` (Blade-like `{{ project.name }}` placeholders), `variables jsonb` (declared + required), `target (chat|cowork|code)`, `skill_keys jsonb`, `version`, `content_hash`.

**skills** — `id`, `key` (= SKILL.md `name`), `title`, `description` (≤200), `version`, `source_path` (`resources/skills/<key>`), `in_plugin bool`, `in_app_agents bool`.
**catalog_task_skill** — `catalog_task_id`, `skill_id`, `required bool`.

**resources** — `id`, `type (article|video|template|tool|vendor|affiliate|internal_doc)`, `title`, `url`, `description_md`, `is_affiliate`, `region jsonb`, `meta`. Pivot **catalog_task_resource** (sort_order, note).

**packs** — `id`, `key`, `name`, `description_md`, `audience jsonb` (`{phase: planning|developing|selling, …}`), `is_default` (exactly one default per phase), `version`, `content_hash`, `status`.
**pack_items** — `pack_id`, `catalog_task_id`, `include_subtree bool`, `sort_order`.

---

## C. Projects (instances)

**projects** — `id ulid`, `workspace_id`, `owner_id`, profile columns from PROJECT_CONTEXT §5 (`name, slug, one_liner, description_md, website_url, primary_domain, business_model, industry, stage, pricing_model, revenue_band, primary_market, target_markets jsonb, languages jsonb, target_customer, problem_statement, solution_summary, legal_entity_status, entity_type, jurisdiction, founded_on, team_size, timezone, currency, goals jsonb, tech jsonb`), `phase (planning|developing|selling)` (chosen at creation, picks the default pack), `status (draft|active|archived)` (new projects start `draft`; setup wizard → `active`), `activated_at`, `context_snapshot_md` (cached), `context_built_at`, `settings jsonb`.

**project_brands** — `project_id` (1:1), `colors jsonb [{name, hex, role}]`, `fonts jsonb`, `voice_md`, `tone jsonb`, `logo_media_id`, `logo_variants jsonb`, `socials jsonb`.

**project_packs** — `project_id`, `pack_id`, `pack_version`, `applied_by`, `applied_at`.

**tasks**

| column                                                          | notes                                                                |
| --------------------------------------------------------------- | -------------------------------------------------------------------- |
| id ulid, project_id, workspace_id                               |                                                                      |
| catalog_task_id?, catalog_version?                              | null ⇒ custom task                                                   |
| parent_id, depth (0–2), sort_order, category_key                | tree; recursive CTE for subtree                                      |
| title, summary, body_md, body_doc                               | copied from catalog, founder-editable                                |
| status                                                          | locked, todo, in_progress, blocked, awaiting_approval, done, skipped |
| priority (p0–p3), due_at, assignee_id (P4)                      |                                                                      |
| completion_criteria jsonb, expected_outputs jsonb               | copied snapshot                                                      |
| verification (none, self_reported, evidence_attached, verified) |                                                                      |
| progress_pct (cached), blocked_reason, skipped_reason           |                                                                      |
| started_at, completed_at, completed_by_type/_id                 |                                                                      |
| has_catalog_update bool                                         | set by catalog-upgrade job                                           |

Indexes: `(project_id, parent_id, sort_order)`, `(project_id, status)`, partial on `status not in ('done','skipped')`.

**task_dependencies** — `task_id`, `depends_on_id`, `kind`.

**task_actions** — `id ulid`, `task_id`, `project_id`, `catalog_action_id?`, `type`, `executor`, `title`, `instructions_md`, `prompt_template_id?`, `prompt_override_md?`, `config jsonb`, `is_required`, `requires_approval`, `status (pending|ready|running|awaiting_input|awaiting_approval|done|failed|skipped)`, `sort_order`, `last_run_id`, `completed_at`, `completed_by_type/_id`.

**action_runs** — `id ulid`, `task_action_id`, `task_id`, `project_id`, `channel (copy_prompt|deep_link|mcp|app_ai|manual|system)`, `actor_type/_id`, `client_name` (e.g. "Claude"), `rendered_prompt` (text, for audit), `status (started|succeeded|failed|cancelled)`, `started_at`, `finished_at`, `output_md`, `output json`, `error`, `usage jsonb` (tokens for app_ai).

**evidence** — `id ulid`, `project_id`, `task_id`, `task_action_id?`, `action_run_id?`, `criterion_key?`, `kind (url|value|file|screenshot|check_result|note)`, `label`, `value text`, `media_id?`, `verified_at?`, `verified_by_type/_id`.

**approvals** — `id ulid`, `project_id`, `subject_type/_id` (TaskAction|Task), `requested_by_type/_id`, `summary_md`, `payload jsonb`, `status (pending|approved|rejected|expired)`, `decided_by`, `decided_at`, `decision_note`.

**comments** _(P4)_ — morph `commentable`, author morph, `body_md`, `resolved_at`.

**decisions** — `id ulid`, `project_id`, `task_id?`, `title`, `decision_md`, `rationale_md`, `alternatives jsonb`, `owner_id`, `decided_on`, `source (user|claude_mcp|app_ai)`, `revisit_on?`.

**media** — `id ulid`, `project_id`, morph owner, `disk`, `path`, `mime`, `size`, `width`, `height`, `alt`, `kind (image|document|export|screenshot)`, `checksum`. Images embedded in Markdown as `![alt](media://<ulid>)`, resolved to signed URLs on render and in MCP output.

---

## D. Knowledge (project brain)

**knowledge_documents** — `id ulid`, `project_id`, `doc_type` (brief, icp, buyer_persona, user_persona, jtbd, positioning, messaging, competitor, interview, research, pricing, brand, prd, sop, policy_draft, decision_record, meeting, other), `title`, `body_md`, `status (draft|approved|archived)`, `source (user|claude_mcp|app_ai|upload|import)`, `task_id?`, `version`, `checksum`, `embedded_at`, `tags jsonb`, `meta`.
Unique "singleton" types per project (icp, positioning, messaging, brand, brief) enforced in code: one _approved_ doc each.

**knowledge_document_versions** — `document_id`, `version`, `body_md`, `created_by_type/_id`, `change_note`.

**knowledge_chunks** — `id`, `document_id`, `project_id`, `chunk_index`, `heading_path`, `content`, `token_count`, `embedding vector(1536)` (HNSW cosine via `->index()`), `tsv tsvector` generated (GIN), `meta`.

Structured research tables (optional P2+, instead of docs when rows matter): **interviews** (person, company, role, date, problem, current_solution, pain, desired_outcome, objections, quotes, feature_requests) and **competitors** (name, url, pricing, icp, positioning, features, integrations, strengths, complaints, last_reviewed_at) — fields taken from the founder checklist. Each row also mirrored to a knowledge doc for search.

---

## E. Activity & audit

**activity_log** — `id`, `workspace_id`, `project_id`, `actor_type (user|agent|system)`, `actor_id`, `client_name?`, `channel (web|mcp|queue|cli)`, `event` (e.g. `task.status_changed`, `action.completed`, `knowledge.saved`, `approval.requested`), `subject_type/_id`, `properties jsonb` (before/after diff, run id), `ip?`, `created_at`. Append-only; index `(project_id, created_at desc)`.
Write through one `ActivityRecorder` service called from model observers/domain actions — never ad hoc.

**notifications** — Laravel default table.

---

## F. Derived rules (enforced in domain layer, covered by tests)

1. Task `locked` while any hard dependency ≠ done/skipped.
2. Action → done requires every `completion_criteria` item linked to it satisfied; `kind=evidence` needs ≥1 evidence row; `kind=check` needs passing check_result.
3. Task auto-done when all required actions done/skipped; parent progress recalculated (job, debounced).
4. `requires_approval` actions cannot leave `awaiting_approval` without an approved `approvals` row.
5. MCP may never: delete projects/tasks, change catalog, change members, approve its own approval.
