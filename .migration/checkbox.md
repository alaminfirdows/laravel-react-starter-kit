# checkbox

2026-09-23, golden pair via CLI, no consumer prop changes needed.

## Changed

- `resources/js/components/ui/checkbox.tsx`: regenerated with `shadcn add checkbox --overwrite -y`. Now imports `Checkbox as CheckboxPrimitive` from `@base-ui/react/checkbox`. Leftover scan clean.

## Left alone

- `resources/js/pages/auth/login.tsx` uses `Checkbox` with a plain boolean `checked`/`onCheckedChange` — no `checked="indeterminate"` usage found (grepped project-wide), so no call-site change was needed; Base UI's `onCheckedChange` signature is compatible.

## Behavior changes

None observed.

## Verify by hand

- On the login page, toggle "Remember me" with mouse click and with keyboard (Tab + Space), confirm the checked state and its visual indicator both update.
