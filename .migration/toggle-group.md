# toggle-group

2026-09-23, golden pair via CLI. No consumers in this project use `ToggleGroup`.

## Changed

- `resources/js/components/ui/toggle-group.tsx`: regenerated with `shadcn add toggle-group --overwrite -y`. Now imports `ToggleGroup as ToggleGroupPrimitive` from `@base-ui/react/toggle-group`. Leftover scan clean.

## Left alone

- No project code outside `ui/` imports `ToggleGroup` (grepped project-wide) — nothing to sweep. Flagging for awareness: if adopted later, note Base UI's `type="single"|"multiple"` and `rovingFocus` prop differences per `consumer-props.md`.

## Behavior changes

None observed (unused).

## Verify by hand

- N/A — component is not currently used anywhere in the app.
