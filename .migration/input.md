# input

2026-09-23, golden pair via CLI, no Radix primitive involved.

## Changed

- `resources/js/components/ui/input.tsx`: regenerated with `shadcn add input --overwrite -y`. `git diff` showed no functional change (Input is a plain native `<input>` wrapper with no Radix dependency in this style). Leftover scan clean.

## Left alone

- All consumers (`passkey-register.tsx`, `password-input.tsx`, `profile.tsx`, `login.tsx`, `register.tsx`, `two-factor-challenge.tsx`, `reset-password.tsx`, `forgot-password.tsx`) use `Input` as a plain controlled/uncontrolled input; nothing to sweep.

## Behavior changes

None.

## Verify by hand

- N/A — no visual or behavioral surface changed.
