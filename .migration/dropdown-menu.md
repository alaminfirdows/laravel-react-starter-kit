# dropdown-menu

2026-09-23, golden pair via CLI + consumer sweep, migrated cleanly.

## Changed

- `resources/js/components/ui/dropdown-menu.tsx`: regenerated with `shadcn add dropdown-menu --overwrite -y`. `DropdownMenuContent`'s `Positioner` now applies `w-(--anchor-width)` internally (renamed from the Radix `--radix-dropdown-menu-trigger-width` CSS var); consumer `className` still merges onto the Positioner as before. Leftover scan clean.
- `resources/js/components/nav-user.tsx`: `DropdownMenuTrigger asChild` → `render={<SidebarMenuButton .../>}`; `w-(--radix-dropdown-menu-trigger-width)` → `w-(--anchor-width)` in the trigger's className; `data-[state=open]:bg-sidebar-accent` → `data-popup-open:bg-sidebar-accent` (Base UI's Trigger exposes open state as the `data-popup-open` presence attribute, not `data-state=open`).
- `resources/js/components/app-header.tsx`: `DropdownMenuTrigger asChild` (wrapping the avatar Button) → `render={<Button .../>}`.
- `resources/js/components/user-menu-content.tsx`: both `DropdownMenuItem asChild` instances (Settings link, Logout link) → `render={<Link>...}`.

## Left alone

- Nothing else in the project renders a `DropdownMenu`.

## Behavior changes

None expected — Base UI dropdown menu items still close the menu on click/select by default in this style, matching Radix's default behavior.

## Verify by hand

- Open the user menu (avatar in header): confirm keyboard arrow-key navigation between items, Escape closes it, clicking "Settings" and "Log out" navigates correctly and closes the menu, and the menu width matches the trigger width (`--anchor-width`).
- Confirm the trigger's `data-popup-open` background highlight shows while the menu is open and clears on close.
