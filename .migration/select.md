# select

2026-09-23, golden pair via CLI. No consumers in this project use `Select`.

## Changed

- `resources/js/components/ui/select.tsx`: regenerated with `shadcn add select --overwrite -y`. Now imports `Select as SelectPrimitive` from `@base-ui/react/select`. Leftover scan clean.

## Left alone

- No project code outside `ui/` imports `Select` (grepped project-wide) — nothing to sweep. Flagging for awareness: if `Select` is adopted later, note Base UI's `onValueChange` signature and `position="popper"`/`position="item-aligned"` props differ from Radix per `consumer-props.md`.

## Behavior changes

None observed (unused).

## Verify by hand

- N/A — component is not currently used anywhere in the app.
