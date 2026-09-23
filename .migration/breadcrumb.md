# breadcrumb

2026-09-23, golden pair via CLI + consumer sweep, migrated cleanly.

## Changed

- `resources/js/components/ui/breadcrumb.tsx`: regenerated with `shadcn add breadcrumb --overwrite -y`. `BreadcrumbLink` keeps its hand-rolled `useRender.ComponentProps<"a">` + `mergeProps` + `useRender` pattern (confirmed via type signature — this is not the real Base UI `Button`/`Link` primitive, so no `nativeButton` prop applies). Leftover scan clean.
- `resources/js/components/breadcrumbs.tsx`: `BreadcrumbLink asChild` wrapping `<Link href={item.href}>{item.title}</Link>` → `BreadcrumbLink render={<Link href={item.href}>{item.title}</Link>}` (drop `asChild`, move children into `render`, per the universal `asChild`→`render` pattern).

## Left alone

- Nothing else references `Breadcrumb*` outside `ui/` and `breadcrumbs.tsx`.

## Behavior changes

None — `render` composition behaves identically to `asChild` here (single static child, no conditional slot).

## Verify by hand

- Navigate to a nested settings page and confirm the breadcrumb trail renders and each link navigates via Inertia (no full page reload).
