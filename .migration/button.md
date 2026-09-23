# button

2026-09-23, golden pair via CLI + consumer sweep, migrated to the real Base UI `Button` primitive.

## Changed

- `resources/js/components/ui/button.tsx`: regenerated with `shadcn add button --overwrite -y`. Now built on the real `Button as ButtonPrimitive` from `@base-ui/react/button` (never a hand-rolled `useRender` wrapper, per the skill's hard rule), typed as `ButtonPrimitive.Props & VariantProps<typeof buttonVariants>`. Leftover scan clean.
- Every consumer using `<Button asChild><X/></Button>` was converted to `<Button render={<X/>} />` (children moved into the `render` element, `asChild` dropped):
  - `resources/js/components/passkey-item.tsx` (Dialog trigger/close buttons)
  - `resources/js/components/delete-user.tsx` (Dialog trigger/close buttons, and the destructive submit `<button>` — `render={<button type="submit" data-test="confirm-delete-user-button">...</button>}`, no `nativeButton` needed since the target is already a native `<button>`)
  - `resources/js/components/app-header.tsx` (Sheet trigger, DropdownMenu trigger)
  - `resources/js/layouts/settings/layout.tsx` (nav Button wrapping `<Link>` — **needs** `nativeButton={false}` since the real Button primitive renders a non-button `<Link>` here; added)

## Left alone

- `resources/js/components/ui/sidebar.tsx`'s `SidebarMenuButton`/`SidebarMenuAction`/`SidebarMenuSubButton` are intentionally NOT the real Button primitive — they're custom `useRender.ComponentProps<"button"|"a">` wrappers with no `nativeButton` prop; verified via their type signatures before leaving them as-is.

## Behavior changes

None expected — `render` + `nativeButton={false}` reproduces the same DOM output as `asChild` did for non-button targets.

## Verify by hand

- Click every migrated button (dialog open/cancel/confirm, sheet trigger, dropdown trigger, settings nav links) and confirm click, keyboard `Enter`/`Space` activation, and focus ring all work as before.
- Specifically check `resources/js/layouts/settings/layout.tsx`'s nav buttons render as `<a>` tags (not nested `<button><a>`) — inspect the DOM.
