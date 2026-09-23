# toggle

2026-09-23, golden pair via CLI. No consumers in this project use `Toggle`.

## Changed

- `resources/js/components/ui/toggle.tsx`: regenerated with `shadcn add toggle --overwrite -y`. Now imports `Toggle as TogglePrimitive` from `@base-ui/react/toggle`. Leftover scan clean.

## Left alone

- No project code outside `ui/` imports `Toggle` (grepped project-wide) — nothing to sweep.

## Behavior changes

None observed (unused).

## Verify by hand

- N/A — component is not currently used anywhere in the app.
