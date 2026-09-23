# sidebar

2026-09-23, golden pair via CLI + consumer sweep, migrated cleanly.

## Changed

- `resources/js/components/ui/sidebar.tsx`: regenerated with `shadcn add sidebar --overwrite -y`. `SidebarMenuButton`, `SidebarMenuAction`, and `SidebarMenuSubButton` keep their hand-rolled `useRender.ComponentProps<"button"|"a">` + `mergeProps` + `useRender` pattern (confirmed via their type signatures — they are not the real Base UI `Button` primitive, so no `nativeButton` prop applies to them). Internally uses the migrated `Collapsible`, `Separator`, `Tooltip`, `Sheet` wrappers. Leftover scan clean.
- Consumer `asChild`→`render` sweep on every `SidebarMenuButton`:
  - `resources/js/components/nav-main.tsx`: `SidebarMenuButton asChild` wrapping `<Link>` → `render={<Link href={item.href} prefetch>...</Link>}`.
  - `resources/js/components/nav-footer.tsx`: `SidebarMenuButton asChild` wrapping `<a>` → `render={<a href={toUrl(item.href)} ...>...</a>}`.
  - `resources/js/components/app-sidebar.tsx`: `SidebarMenuButton size="lg" asChild` wrapping the dashboard `<Link>` → `render={<Link href={dashboard()} prefetch><AppLogo /></Link>}`.
  - `resources/js/components/nav-user.tsx`: `DropdownMenuTrigger asChild` wrapping `SidebarMenuButton` → `render={<SidebarMenuButton .../>}` (documented under `dropdown-menu.md`).

## Left alone

- Nothing else in the project renders sidebar primitives.

## Behavior changes

None expected — `render` composition on these custom `useRender` wrappers reproduces the same DOM as `asChild` did.

## Verify by hand

- Click every sidebar nav item (main nav links, footer links, the dashboard logo link) and confirm navigation and active-state styling. Collapse/expand the sidebar and confirm tooltips show item labels when collapsed.
