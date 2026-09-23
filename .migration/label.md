# label

2026-09-23, golden pair via CLI. Base UI has no `Label` primitive (per the skill: no counterpart), so the shadcn `base-vega` variant of this wrapper uses a native `<label>` element.

## Changed

- `resources/js/components/ui/label.tsx`: regenerated with `shadcn add label --overwrite -y`. No longer imports any Radix/Base UI primitive; renders a plain `<label>`. Leftover scan clean.

## Left alone

- All consumers (`delete-user.tsx`, `passkey-register.tsx`, `profile.tsx`, `confirm-password.tsx`, `security.tsx`, `login.tsx`, `register.tsx`, `reset-password.tsx`, `forgot-password.tsx`) use `Label` with a plain `htmlFor`; nothing to sweep.

## Behavior changes

None — native `<label for>` association behaves identically to Radix's `Label.Root`.

## Verify by hand

- Click a form label and confirm focus moves to its associated input (e.g. the password label on the login page).
