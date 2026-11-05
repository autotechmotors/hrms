# Work order

Do the steps in this order. Check each one before the next. Read the module file named on the step before editing.

1. `docs/roles/NAV-PERMISSIONS.md` — menu and controller checks, with seed grants so current roles do not lose screens.
2. `docs/employees/PROFILE.md` — form gaps and the per-person attendance card.
3. `docs/attendance/REGISTER.md` — phone column and the phone card layout for past and current days.
4. `docs/prompts/HRMS-BRAND.md` and `docs/prompts/HRMS-LIQUID-GLASS.md` — icon, tokens, chrome.
5. `docs/prompts/HRMS-PWA.md` — manifest, icons, service worker.

Other module files are freeze notes. Open one only when that screen is in the task. Do not restyle them ahead of this order.

## Check after each step

- Desktop about 1280px, phone about 390px, and one iPhone safe-area case.
- Open the menu.
- Open attendance for today and for a past day.
- A Manager cannot open payroll or devices.

## Done when

- Each role's menu matches `docs/roles/NAV-PERMISSIONS.md`, and rows are still scope-filtered.
- Employee create and edit save alternate phone, confirmation date, and address fields that already exist on the model.
- Today and any past day list each visible employee with phone, in, out, worked hours, and status, with no horizontal page scroll on a phone.
- The installed icon is the red triangle. Accent is that red. Status green, amber, and red are unchanged.
- Chrome is liquid glass, with a solid fallback. iPhone safe areas are padded. The desktop sidebar still works.
- Login, MFA, punches, devices, payroll, and imports behave as they did before the visual pass. See `docs/prompts/HRMS-FREEZE.md`.
