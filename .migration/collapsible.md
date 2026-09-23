# collapsible

2026-09-23, golden pair via CLI, no direct consumers outside `sidebar.tsx`.

## Changed

- `resources/js/components/ui/collapsible.tsx`: regenerated with `shadcn add collapsible --overwrite -y`. Now imports `Collapsible as CollapsiblePrimitive` from `@base-ui/react/collapsible`. Leftover scan clean.

## Left alone

- `resources/js/components/ui/sidebar.tsx` uses `Collapsible` internally for the collapsible sidebar groups — no `asChild` usage there, so no change was needed beyond the wrapper regeneration itself.

## Behavior changes

None observed.

## Verify by hand

- Collapse/expand a sidebar group with a submenu and confirm the open/close animation and keyboard toggle (Enter/Space on the trigger) still work.
