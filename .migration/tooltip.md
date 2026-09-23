# tooltip

2026-09-23, golden pair via CLI + consumer sweep, migrated cleanly.

## Changed

- `resources/js/components/ui/tooltip.tsx`: regenerated with `shadcn add tooltip --overwrite -y`. `TooltipProvider` now takes `TooltipPrimitive.Provider.Props` with `delay = 0` default (renamed from Radix's `delayDuration`). Leftover scan clean.
- `resources/js/app.tsx`: `<TooltipProvider delayDuration={0}>` → `<TooltipProvider delay={0}>` per the documented Base UI rename. This was caught by a new `tsc` error (`Property 'delayDuration' does not exist on type 'IntrinsicAttributes & TooltipProviderProps'`) after the wrapper regeneration, confirming the rename was required, not optional.

## Left alone

- `resources/js/components/app-header.tsx`'s `TooltipTrigger` wrapping a bare `<a>` (Repository/Documentation icons) was left unchanged — it has no `asChild`/`render` prop in either Radix or Base UI at that call site, so behavior is identical; this is not a migration artifact.

## Behavior changes

None expected — `delay={0}` reproduces the same "no show-delay" behavior as `delayDuration={0}` did.

## Verify by hand

- Hover the Repository/Documentation icon buttons in the header and confirm the tooltip appears with the expected (near-instant) delay and correct label text.
