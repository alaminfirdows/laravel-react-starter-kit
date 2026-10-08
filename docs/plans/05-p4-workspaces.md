# P4 Team Workspaces — Outline

> **For agentic workers:** OUTLINE. Before execution, re-run superpowers:writing-plans on this file to expand each task into full TDD steps against the code that exists after P3.

**Goal:** Teams work together in a workspace: invite members, assign tasks, comment with mentions, per-member MCP tokens, an audit view, and live updates.

**Architecture:** Reuse the existing `App\Domain\Workspace` roles and `WorkspacePermission`. Add invitations, `tasks.assignee_id` usage, morph `comments`. Broadcast domain events on private `workspace.{id}` / `project.{id}` channels with Laravel Reverb; frontend listens with `@laravel/echo-react` and does Inertia partial reloads.

**Spec:** `docs/DATA_MODEL.md` §A (`workspace_invitations`), §C `comments`, `tasks.assignee_id`, §E.

## Global Constraints

- Every broadcast channel authorizes membership (`routes/channels.php`).
- Invitations expire after 7 days; token single use.
- Role checks only via `WorkspaceRole::isAtLeast` / `WorkspacePermission`.

## Review Focus

1. Invitation link used by a different logged-in email → refused.
2. Removed member still subscribed to a channel → no further events.
3. Mention of a non-member → no notification, plain text.
4. Assignee removed from workspace → task unassigned, not orphaned.
5. Viewer posting a comment → refused (or allowed — decide at expansion; default refused).

## Tasks

| # | Task | Files / classes | Tests |
| --- | --- | --- | --- |
| 1 | Invitations | migration `workspace_invitations`; `Workspace\Actions\{InviteMember,AcceptInvitation,RevokeInvitation}`; mail | `InvitationTest` (Review Focus 1) |
| 2 | Members UI | `settings/workspace/members` page: list, change role, remove (`RemoveMember` unassigns tasks) | `MembersHttpTest` (Review Focus 4) |
| 3 | Assignment | `Task\Actions\AssignTask`; assignee picker on task page; "My tasks" page | `AssignTaskTest` |
| 4 | Comments | migration `comments`; `Comment\Actions\{PostComment,ResolveComment}`; `@mention` parser → notifications | `CommentTest` (Review Focus 3, 5) |
| 5 | Per-member MCP tokens | tokens list per member on Connect Claude page; admin can revoke others' tokens | `McpTokensTest` |
| 6 | Audit view | `projects/activity` page (filters: actor, channel, event); workspace-level activity for admins | `ActivityHttpTest` |
| 7 | Reverb | `composer require laravel/reverb`; events `TaskStatusChanged`, `CommentPosted`, `RunFinished` (ShouldBroadcast); channel auth | `BroadcastAuthTest` (Review Focus 2) |
| 8 | Live UI | `useEcho` hooks → `router.reload({ only: ['tree','task'] })`; replace P1 polling | manual smoke |
| 9 | Docs + gate | `composer ci:check` | — |
