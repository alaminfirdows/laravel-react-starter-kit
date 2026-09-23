# avatar

2026-09-23, golden pair via CLI, migrated cleanly with zero customizations to preserve.

## Changed

- `resources/js/components/ui/avatar.tsx`: regenerated with `shadcn add avatar --overwrite -y` against the `base-vega` style. Now imports `Avatar as AvatarPrimitive` from `@base-ui/react/avatar` instead of `radix-ui`. `git diff` showed no other change beyond the import source and primitive namespace.
- Leftover scan: `grep -n "radix-ui\|@radix-ui" resources/js/components/ui/avatar.tsx` → clean.

## Left alone

- No consumer files needed changes. `Avatar`/`AvatarImage`/`AvatarFallback` are used directly (no `asChild`) in `resources/js/components/user-info.tsx` and `resources/js/components/app-header.tsx`; both compile and render identically.

## Behavior changes

None observed.

## Verify by hand

- Load a page with a user avatar (header, user menu): confirm the image renders, and confirm the fallback initials show when the avatar image URL 404s or is empty.
