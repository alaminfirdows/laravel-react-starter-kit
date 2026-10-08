# MCP Server, Prompts & Skills Spec

## 1. Server

- Package: `laravel/mcp` (1.x). Class `App\Mcp\Servers\FounderServer`, registered in `routes/ai.php`:
    ```php
    Mcp::oauthRoutes();                                   // discovery + DCR (Passport)
    Mcp::web('/mcp/founder', FounderServer::class)
        ->middleware(['auth:api', 'throttle:mcp']);
    Mcp::local('founder', FounderServer::class);          // dev / MCP Inspector
    ```
- Auth: Passport OAuth 2.1 (PKCE, DCR). User adds `https://[DOMAIN]/mcp/founder` as a custom connector in Claude → consent screen (our branded Passport view: shows client name, scopes, which projects). Keep scope minimal (`mcp:use`).
- Authorization inside every tool: `$request->user()` + existing Policies (`ProjectPolicy`, `TaskPolicy`). Never trust IDs from the model; always re-scope by user's workspace.
- Server `#[Instructions]`: short — "Always call `get_task` before working on a task. Never mark complete without evidence when criteria require it. Use `request_approval` before irreversible actions."
- Large tool set → keep hot tools advertised, put the rest in a `ToolSearch` catalog (MCP 1.0).
- Every tool call writes `activity_log` (actor_type=agent, client_name from token client) and, when tied to an action, an `action_runs` row.
- Tool output: compact Markdown (+ `structuredContent` JSON where useful). Paginate lists. Resolve `media://` to short-lived signed URLs.

## 2. Tools

### Shipped (P1)

- Tools: `whoami`, `list_projects`, `get_project_context`, `list_tasks`, `get_task`, `get_action` (read-only); `start_action`, `save_output`, `attach_evidence` (kinds `url`/`value`/`note`; `check_result` is app-only), `request_approval`, `complete_action` (write, need `update` on project).
- Prompt: `run-task` (`task_id`) → launcher for first open action.
- Resources: `founder://projects/{project_id}/context`, `founder://tasks/{task_id}`.
- Errors: foreign/unknown ID → "not found"; viewer write → permission error; invalid transition → tool error.
- Plugin: `php artisan plugin:build --release=1.0.0 --skills=resources/skills --output=plugin` → `plugin/` + `storage/app/plugins/founder-os-<version>.zip`. Skills ship unless frontmatter `metadata.in_plugin: false`.
- Inspector: `php artisan mcp:inspector founder`.

Rows below = full target spec; P2+ adds knowledge tools.

### Advertised (hot)

| Tool                  | Purpose                                                                                    | Key args                                                     | Annotations |
| --------------------- | ------------------------------------------------------------------------------------------ | ------------------------------------------------------------ | ----------- |
| `whoami`              | Account, workspace, projects list                                                          | –                                                            | read-only   |
| `get_project_context` | Context snapshot: profile, brand, goals, approved strategy docs (summaries + IDs)          | project_id, sections[]                                       | read-only   |
| `get_task`            | Task + subtasks + actions + criteria + resources + required skills + rendered instructions | task_id                                                      | read-only   |
| `list_tasks`          | Filtered list (status, category, ready-to-do)                                              | project_id, filters, cursor                                  | read-only   |
| `start_action`        | Mark action running, open ActionRun, return run_id                                         | action_id                                                    | –           |
| `complete_action`     | Finish run with output + evidence; server validates criteria                               | action_id, run_id, output_md, evidence[]                     | –           |
| `search_knowledge`    | Hybrid semantic search over project docs                                                   | project_id, query, doc_types[], limit                        | read-only   |
| `save_knowledge`      | Create/update doc (new version), link to task                                              | project_id, doc_type, title, body_md, task_id?, document_id? | –           |

### Catalog (via `search_tools` / `execute_tools`)

`get_document`, `list_documents`, `update_task_status` (todo/in_progress/blocked/skipped only — `done` derives from actions), `report_blocker`, `request_approval`, `get_approval_status`, `attach_evidence`, `add_note`, `log_decision`, `submit_input` (for input actions the founder answered in chat), `create_followup_task` (custom task, flagged `source=agent`), `get_brand`, `upload_file` (base64 or URL → media), `list_resources`.

### Resources (MCP resources)

`founder://projects/{id}/brief` (context snapshot Markdown), `founder://projects/{id}/brand`, `founder://tasks/{id}`. Useful for clients that attach resources; tools remain the primary path.

### Prompts (MCP prompts)

`run-task` (args: task_id) → returns the launcher text. Lets users start from Claude's prompt menu instead of our deep link. **VERIFY** current Claude UI surfacing of MCP prompts.

## 3. Completion protocol (server-enforced + skill-taught)

1. `get_task` → read criteria/expected outputs.
2. `start_action`.
3. Do the work (skills, Chrome, files).
4. Persist outputs: `save_knowledge` / `upload_file`.
5. `attach_evidence` per criterion (URLs, IDs like GA4 measurement_id, screenshots).
6. `complete_action` → server checks criteria → returns `done` or the list of unmet criteria.
7. Irreversible step? → `request_approval` first and stop.
8. Suggest follow-ups (`create_followup_task` only if user agrees).

## 4. Prompt templates

### Launcher (deep link `claude://claude.ai/new?q=` or `claude://cowork/new?q=`)

```
Use the [PRODUCT_NAME] connector and the founder-os-task-runner skill.
Project: {{project.id}} · Task: {{task.id}} · Action: {{action.id}}
1. Call get_task for the task above and follow its instructions and skills.
2. Follow the completion protocol. Ask me before anything irreversible.
```

Rules: no company data in the URL; URL-encode; stay far below the ~14k `q` limit; target `cowork` for `file`/`browser` actions, `chat` otherwise. The user still presses Send — the UI must say so.

### Full (copy button)

Rendered server-side: role line → task title/instructions → inlined context (`{{project.*}}`, `{{brand.*}}`, approved docs truncated by budget) → output format → "paste the result back into [PRODUCT_NAME] task {{task.id}}". Budget-trim long docs (summaries first).

## 5. Plugin (distribution of skills + connector)

Source of truth: `resources/skills/*`. Artisan `php artisan plugin:build` assembles:

```
founder-os/
├── .claude-plugin/plugin.json
├── .mcp.json                 # remote connector → https://[DOMAIN]/mcp/founder
└── skills/
    ├── founder-os-task-runner/SKILL.md   # protocol, always relevant
    ├── icp-definition/SKILL.md
    ├── competitor-research/…
    ├── positioning/ · pricing-strategy/ · seo-technical-audit/
    ├── google-search-console-setup/ · ga4-setup/ · email-dns-setup/
    └── …
```

Then zips → `storage/app/plugins/founder-os-<version>.zip`, served from the "Connect Claude" page; optionally also publish to a Git marketplace repo. No top-level `bin/` (blocks claude.ai/Cowork install). **VERIFY** manifest fields against current plugin docs before release.

Skill authoring rules: folder = `name` (kebab, ≤64, no "claude"/"anthropic"); description ≤200 chars, says _what + when_, slightly "pushy"; body < 500 lines; details in `references/`; skills never contain project data — they call MCP. Version every skill; `skills.version` mirrored in DB so the app can tell the user "your plugin is outdated".

The same folders are loaded by in-app agents via `laravel/ai` `HasSkills`, so a skill improves both paths at once.

## 6. Security checklist (MCP)

- OAuth only on web transport; no session auth; bearer validated per request.
- No token passthrough to third parties; third-party credentials never exposed to Claude.
- Rate limit per user + per client (`throttle:mcp`).
- Tool-level authorization with Policies; `shouldRegister()` to hide tools by plan/role.
- Input validation on every tool (`$request->validate`); IDs must belong to user's workspace.
- Treat all model-supplied text as untrusted (escape on render, sanitize Markdown → HTML).
- Users can list/revoke connected clients in Settings → Connections.
