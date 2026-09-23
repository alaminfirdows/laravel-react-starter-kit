# dialog

2026-09-23, golden pair via CLI + consumer sweep, migrated cleanly.

## Changed

- `resources/js/components/ui/dialog.tsx`: regenerated with `shadcn add dialog --overwrite -y`. Portal→Positioner→Popup structure now sourced from `@base-ui/react/dialog`; Overlay renamed internally to Backdrop per Base UI's anatomy. Leftover scan clean.
- Consumer `asChild`→`render` sweep on every `DialogTrigger`/`DialogClose`:
  - `resources/js/components/passkey-item.tsx`: trigger button (Trash2 icon) and Cancel close button.
  - `resources/js/components/delete-user.tsx`: "Delete account" trigger button, "Cancel" close button, and the destructive submit button (real `Button` primitive, native `<button>` target, no `nativeButton` needed).

## Left alone

- Nothing else in the project renders a `Dialog`.

## Behavior changes

None expected — Base UI's Dialog focus-trap and Escape/outside-click-to-close behavior match shadcn's default configuration for this style.

## Verify by hand

- Open the "Remove passkey" dialog and the "Delete account" dialog: confirm focus moves into the dialog on open, Escape closes it, clicking the backdrop closes it, and focus returns to the trigger button on close.
- Confirm the destructive submit button still submits the form (Inertia `Form` component) and shows validation errors on the password field.
