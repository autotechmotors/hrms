# HRMS system

Keywords for Cursor: `liquid glass`, `liquid-glass`, `iOS liquid glass`, `frosted glass`, `glassmorphism`, `PWA`, `iPhone layout`, `safe-area`, `app icon`.

This Laravel HRMS stays Blade, Bootstrap 5, jQuery, Select2, and Flatpickr. There is no Vite and no npm build. Do not rebuild it.

The signed-in person sees only their department, role, and permitted modules. Attendance they are allowed to see shows past and current days with phone and worked hours. The site installs as a PWA. Chrome uses iPhone liquid glass. The mark is `docs/brand/app-icon.png`.

## Read order

1. `docs/prompts/HRMS-FREEZE.md` before any edit.
2. `docs/prompts/HRMS-LIVE-UI.md` before a filter, list, form, delete, or pagination change.
3. The one module file for the screen you are changing (`docs/README.md` lists them).
4. `docs/prompts/HRMS-BRAND.md`, `HRMS-LIQUID-GLASS.md`, and `HRMS-PWA.md` only when the task is visual or installable.
5. `docs/prompts/HRMS-WORK-ORDER.md` when more than one module is in the same pass.

## Where a new note goes

| Kind | Path | Name |
|---|---|---|
| System rule that is not one screen | `docs/prompts/` | `HRMS-<TOPIC>.md` |
| One screen or one behaviour | `docs/<module>/` | `<TOPIC>.md` |
| Icon file | `docs/brand/` | `app-icon.png` only |

One topic per file. If the topic already has a file, edit that file. If it does not, create the file and add a row to `docs/README.md` in the same change. Do not paste a second topic into an existing note.

"All employees" means every employee `visibleTo` the signed-in user. It is never a scope bypass.
