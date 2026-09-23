# sheet

2026-09-23, golden pair via CLI + consumer sweep, migrated cleanly.

## Changed

- `resources/js/components/ui/sheet.tsx`: regenerated with `shadcn add sheet --overwrite -y`. Built on the migrated `@base-ui/react/dialog` primitive (Sheet is a styled Dialog variant in shadcn). Leftover scan clean.
- `resources/js/components/app-header.tsx`: `SheetTrigger asChild` (wrapping the mobile-menu hamburger Button) → `render={<Button variant="ghost" size="icon" ...><Menu .../></Button>}`.

## Left alone

- Nothing else in the project renders a `Sheet`.

## Behavior changes

None expected — same underlying Dialog primitive/behavior as before.

## Verify by hand

- On a narrow viewport, open the mobile menu (hamburger icon): confirm it slides in from the left, Escape/backdrop-click closes it, and both nav link groups (main + repository/docs) work and close the sheet on navigation.
