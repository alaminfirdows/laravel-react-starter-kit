# navigation-menu

2026-09-23, golden pair via CLI, no consumer prop changes needed.

## Changed

- `resources/js/components/ui/navigation-menu.tsx`: regenerated with `shadcn add navigation-menu --overwrite -y`. Now imports `NavigationMenu as NavigationMenuPrimitive` from `@base-ui/react/navigation-menu`. Leftover scan clean.

## Left alone

- `resources/js/components/app-header.tsx` uses `NavigationMenu`/`NavigationMenuList`/`NavigationMenuItem` with plain `<Link>` children and `navigationMenuTriggerStyle()` classes — no `asChild`, no `delayDuration`/`viewport` overrides, so no call-site change was needed.
- Per the skill: `NavigationMenu` `Indicator` has no Base UI equivalent (inert passthrough) — not used in this project, so no flag needed in practice.

## Behavior changes

None observed.

## Verify by hand

- On desktop width, confirm the "Dashboard" nav item shows its active-state underline and hover styling correctly.
