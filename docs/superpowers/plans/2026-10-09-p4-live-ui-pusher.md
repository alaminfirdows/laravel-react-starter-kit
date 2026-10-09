# P4 Live UI (Pusher) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development.

**Goal:** Project pages update live over Pusher instead of polling.

**Architecture:** Server already broadcasts `TaskStatusChanged`, `RunFinished`, `CommentPosted` on private channel `projects.{projectId}` (`ProjectChannel`). Add stable `broadcastAs()` names, Pusher env config, Echo client (`@laravel/echo-react` `configureEcho`), and one hook `useProjectChannel` in the project layout that calls `router.reload({ only: [...] })` on each event. Remove `usePoll` from task page.

**Tech Stack:** Laravel 13, pusher/pusher-php-server ^7.3 (installed, uncommitted composer.json/lock), Inertia React v3, `@laravel/echo-react`, `laravel-echo`, `pusher-js`.

**Spec:** `docs/plans/05-p4-workspaces.md` Tasks 7–8 (Reverb replaced by Pusher — user decision).

## Global Constraints

- Branch `dev`, commit per task. Conventional commit messages (`feat:`, `chore:`, `docs:`).
- No new base folders; do not edit `resources/js/components/ui`.
- PHP: `vendor/bin/pint --dirty --format agent`; `vendor/bin/phpstan analyse --memory-limit=1G` clean, no ignore comments / inline `@var`.
- Frontend: `pnpm run check:fix`, `pnpm run types:check`, `pnpm run check` clean.
- Tests: `php artisan test --compact <path>`. phpunit uses `BROADCAST_CONNECTION=null`.
- Events carry ids only (no task titles, no bodies).
- Echo must not crash when `VITE_PUSHER_APP_KEY` is empty (local dev without Pusher): skip `configureEcho` and make the hook a no-op.

## Review Focus

1. No Pusher key configured → app boots, project pages render, no console exceptions.
2. User on project A never subscribes to project B channel (channel name from current `project.id`).
3. Navigating between tasks of same project → one subscription, not leaked listeners.
4. Event for a different task → reload still cheap (`only` partial props), no full page reload.
5. Task page with an active run → status updates via `RunFinished` without polling.

---

### Task 1: Pusher config + stable event names

**Files:**

- Modify: `.env.example` (add `PUSHER_APP_ID=`, `PUSHER_APP_KEY=`, `PUSHER_APP_SECRET=`, `PUSHER_APP_CLUSTER=mt1`, `VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"`, `VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"`; set `BROADCAST_CONNECTION=log` with comment line `# set to pusher in production`)
- Modify: `app/Domain/Task/Events/TaskStatusChanged.php`, `app/Domain/Task/Events/RunFinished.php`, `app/Domain/Comment/Events/CommentPosted.php` — add `public function broadcastAs(): string` returning `'task.status-changed'`, `'run.finished'`, `'comment.posted'`.
- Commit also: `composer.json`, `composer.lock` (pusher/pusher-php-server already required).
- Test: `tests/Feature/Broadcasting/BroadcastAuthTest.php` — extend existing event tests to assert `broadcastAs()` values; add test that `config('broadcasting.connections.pusher.driver') === 'pusher'`.

**Produces:** event names `task.status-changed`, `run.finished`, `comment.posted` (client listens with leading dot: `.task.status-changed`).

- [ ] Write failing assertions, run, implement, run `php artisan test --compact tests/Feature/Broadcasting`, pint, phpstan.
- [ ] Commit: `feat: pusher broadcasting config and event names`.

### Task 2: Echo client + live project layout

**Files:**

- `pnpm add @laravel/echo-react laravel-echo pusher-js` (approved by user).
- Modify: `resources/js/app.tsx` — before `createInertiaApp`:
    ```ts
    import { configureEcho } from '@laravel/echo-react';
    if (import.meta.env.VITE_PUSHER_APP_KEY) {
        configureEcho({
            broadcaster: 'pusher',
            key: import.meta.env.VITE_PUSHER_APP_KEY,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
            forceTLS: true,
        });
    }
    ```
    Check installed `@laravel/echo-react` README / types for exact `configureEcho` options; adjust to match. Add env typings to `resources/js/types/vite-env.d.ts` (or existing env d.ts) if needed.
- Create: `resources/js/hooks/use-project-channel.ts` — `useProjectChannel(projectId: string): void`. Uses `useEcho(\`projects.${projectId}\`, ['.task.status-changed', '.run.finished', '.comment.posted'], () => router.reload({ only: ['tree', 'task', 'comments'] }))`. When no Pusher key, return early without calling Echo (hooks rules: split into a component or guard inside a stable wrapper — e.g. render `<ProjectChannelListener projectId>`only when key exists). Inertia ignores`only`keys that the page lacks; verify`comments` is the prop name on task show page.
- Modify: `resources/js/layouts/project-layout.tsx` — subscribe for `project.id`.
- Modify: `resources/js/pages/projects/tasks/show.tsx` — remove `usePoll` + `useEffect` polling block and unused imports.
- Check: `grep -rn usePoll resources/js` returns nothing afterwards.
- Run `pnpm run check:fix`, `pnpm run types:check`, `pnpm run check`, `pnpm run build`, then `php artisan test --compact tests/Feature/Task tests/Feature/Project` (pages still render).
- [ ] Commit: `feat: live project updates via echo`.

### Task 3: Docs

- Modify: `docs/plans/05-p4-workspaces.md` Shipped notes — Tasks 7–8 shipped with Pusher (not Reverb: psr7 conflict); env keys; event names; polling removed; no-key fallback.
- Modify: `docs/CLAUDE.md` rule 13 — "Reverb in P4" → "Pusher via Echo".
- [ ] Commit: `docs: p4 live ui via pusher`.
