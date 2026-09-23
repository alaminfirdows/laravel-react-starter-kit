# badge

2026-09-23, golden pair via CLI, no Radix primitive involved.

## Changed

- `resources/js/components/ui/badge.tsx`: regenerated with `shadcn add badge --overwrite -y` against `base-vega`. `git diff` showed no functional change (badge has no Radix dependency; it's a plain `span`/`Slot`-free component in this style). Re-verified with `grep -n "radix-ui\|@radix-ui" resources/js/components/ui/badge.tsx` → clean.

## Left alone

- No consumer files reference `Badge` outside `ui/` in this project; nothing to sweep.

## Behavior changes

None.

## Verify by hand

- N/A — no visual or behavioral surface changed.
