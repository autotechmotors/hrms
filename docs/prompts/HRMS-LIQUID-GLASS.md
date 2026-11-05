# Liquid glass

Light theme stays. Liquid glass is the chrome: sidebar, top bar, bottom bar, menus, and sheets. Numbers stay on solid cards.

## Chrome

- `backdrop-filter: var(--glass-blur)` plus a 1px specular edge and a soft shadow
- Sticky top bar and the phone bottom bar use the same glass
- Desktop: left sidebar column
- Under 900px: sidebar is a drawer
- Under 700px: primary destinations sit in the glass bottom bar, padded with `env(safe-area-inset-bottom)`
- The bottom bar lists only modules that role may open, capped at five: Dashboard, Employees, Attendance, then the next permitted items. The rest go in a More sheet. Permission rules: `docs/roles/NAV-PERMISSIONS.md`
- Touch targets at least 44px on coarse pointers
- `viewport-fit=cover` stays. Pad the notch and the home indicator
- `prefers-reduced-transparency: reduce` uses solid `--sf`
- No `backdrop-filter` support uses solid `--sf`

## Widths

Reuse the breakpoints already in `public/assets/css/app.css`: 1200, 900, 700.

At 700px, `.c2` `.c3` `.c4` `.c6` stack to one column, page search hides, and wide tables other than attendance scroll inside `.tw`. Attendance uses the card stack in `docs/attendance/REGISTER.md`. `html` and `body` keep `overflow-x: clip`.

Login and two-factor: one glass card on `--bg`. Do not weaken the forms. Colour tokens: `docs/prompts/HRMS-BRAND.md`.
