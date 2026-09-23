# separator

2026-09-23, golden pair via CLI, no consumer prop changes needed.

## Changed

- `resources/js/components/ui/separator.tsx`: regenerated with `shadcn add separator --overwrite -y`. Now imports `Separator as SeparatorPrimitive` from `@base-ui/react/separator`. Leftover scan clean.

## Left alone

- `resources/js/components/passkey-verify.tsx` and `resources/js/layouts/settings/layout.tsx` use `Separator` with only `className` — nothing to sweep.

## Behavior changes

None observed.

## Verify by hand

- Confirm the visual divider still renders correctly on the settings layout and the passkey verify page.
