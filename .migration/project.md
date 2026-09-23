# project — Radix UI → Base UI (whole project)

2026-09-23, whole-project mode: apply preset first, then migrate, full dependency swap.

## Preset step (flagged, no-op)

`pnpm dlx shadcn@latest apply --preset bIkeymG -y` skipped all 26 files ("might be identical") and produced zero diff on `components.json`/`app.css`. This is expected: presets carry theme tokens (colors, radius, fonts), not the primitive engine, and this project's current theme already matched the preset exactly. It did not advance the Radix→Base swap itself; that swap was done separately via the style flip + golden-pair CLI regeneration below.

## Style flip

`components.json` style: `radix-vega` → `base-vega` (commit `6a9ec53`). This makes `shadcn add <component> --overwrite -y` deliver the Base UI variant of each wrapper going forward.

## Dependency swap

- Added: `@base-ui/react` `1.8.0` (commit `6a9ec53`).
- Removed (this session, `pnpm remove`): `@radix-ui/react-avatar`, `@radix-ui/react-checkbox`, `@radix-ui/react-collapsible`, `@radix-ui/react-dialog`, `@radix-ui/react-dropdown-menu`, `@radix-ui/react-label`, `@radix-ui/react-navigation-menu`, `@radix-ui/react-select`, `@radix-ui/react-separator`, `@radix-ui/react-slot`, `@radix-ui/react-toggle`, `@radix-ui/react-toggle-group`, `@radix-ui/react-tooltip`, `radix-ui` — 14 packages, `pnpm-lock.yaml` updated (69 packages removed from the dependency tree).

## Wrapper migration (18 components, all via golden-pair CLI)

`avatar`, `badge`, `breadcrumb`, `button`, `checkbox`, `collapsible`, `dialog`, `dropdown-menu`, `input`, `label`, `navigation-menu`, `select`, `separator`, `sheet`, `sidebar`, `toggle`, `toggle-group`, `tooltip` — each regenerated with `shadcn add <component> --overwrite -y` against `base-vega` and verified via `git diff`. Per-component detail in `.migration/<component>.md`.

Classification method: initial manual diffing against raw registry JSON (`https://ui.shadcn.com/r/styles/radix-vega/<c>.json`) produced false-positive "customizations" (docs-site `IconPlaceholder` artifacts, unresolved `cn-font-heading` template tokens, stray `"use client"` differences) — pivoted to using the `shadcn add --overwrite` CLI itself as ground truth (it does correct icon/alias/template resolution) combined with `git diff`. This revealed the true customization set was **empty**: every wrapper in this project was pristine, so no 3-way merges were needed despite that being the approved fallback strategy.

4 files needed no change (no Radix primitive in this style): `alert.tsx`, `card.tsx`, `skeleton.tsx`, `spinner.tsx`.

2 files intentionally left untouched (non-Radix libraries, per skill hard rule): `sonner.tsx`, `input-otp.tsx`.

2 non-registry custom files, no shadcn counterpart, untouched: `icon.tsx`, `placeholder-pattern.tsx`.

## App-code (consumer) sweep

Every `asChild` prop in `resources/js` (outside `ui/`) was converted to Base UI's `render` prop, and every documented prop/attribute rename from `consumer-props.md` was applied:

- `resources/js/components/passkey-item.tsx` — Dialog trigger/close.
- `resources/js/components/delete-user.tsx` — Dialog trigger/close/destructive-submit.
- `resources/js/components/nav-main.tsx` — SidebarMenuButton wrapping `<Link>`.
- `resources/js/components/nav-footer.tsx` — SidebarMenuButton wrapping `<a>`.
- `resources/js/components/breadcrumbs.tsx` — BreadcrumbLink wrapping `<Link>`.
- `resources/js/components/nav-user.tsx` — DropdownMenuTrigger wrapping SidebarMenuButton; `--radix-dropdown-menu-trigger-width` → `--anchor-width`; `data-[state=open]` → `data-popup-open`.
- `resources/js/components/app-sidebar.tsx` — SidebarMenuButton wrapping dashboard `<Link>`.
- `resources/js/components/app-header.tsx` — SheetTrigger, DropdownMenuTrigger.
- `resources/js/components/user-menu-content.tsx` — two DropdownMenuItem instances wrapping `<Link>`.
- `resources/js/layouts/settings/layout.tsx` — Button wrapping `<Link>` (added `nativeButton={false}`, the one case using the real Button primitive against a non-button target).
- `resources/js/app.tsx` — `TooltipProvider delayDuration` → `delay`.

Grepped project-wide for remaining risk patterns (`data-[state=`, `--radix-`, `data-[side=`, `data-[orientation=`, `data-[disabled`, `data-[highlighted`, `data-[motion`, `delayDuration`, `checked="indeterminate"`, `type="single"|"multiple"`, `position="popper"|"item-aligned"`, etc.) — none remain outside the migrated `ui/` wrappers.

Per-component detail (including files intentionally left alone, e.g. `TooltipTrigger` wrapping a bare `<a>` in `app-header.tsx`, which needed no change in either engine) is in each `.migration/<component>.md`.

## Verification

- `pnpm run types:check`: only the 13 pre-existing baseline TS errors remain (confirmed identical before and after all edits, and again after dependency removal) — no new errors introduced. Baseline errors: `auth` typed `unknown` in `app-header.tsx`/`nav-user.tsx`/`welcome.tsx`, `unknown` not assignable to `ReactNode`/`boolean` in `app-logo.tsx`/`app-shell.tsx`/`auth-split-layout.tsx`, and a missing `@inertiajs/core` type declaration in `passkey-verify.tsx`. All pre-existing and out of scope for this migration.
- `pnpm run build`: succeeds, 2826 modules transformed, no errors.
- `pnpm run check` (lint/format): 4 pre-existing formatting issues remain unchanged from baseline (`components.json`, `resources/css/app.css`, `resources/js/hooks/use-mobile.ts`, `skills-lock.json` — confirmed via `git stash` + re-run, none touched by this migration). One new formatting issue introduced by this migration's edit to `resources/js/components/passkey-item.tsx` was fixed with `vp check --fix` on that file only.

## Leftover scan (final)

`grep -rln "radix-ui\|@radix-ui\|IconPlaceholder" resources/js` → **0 matches**.

**0 wrappers remain on Radix.**
