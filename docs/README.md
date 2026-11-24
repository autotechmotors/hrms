# Docs

Brand assets, system prompts, and module notes each have one place. One topic per file. A new note gets a new file. It does not get appended to a file that already exists.

| Kind | Path | File name |
|---|---|---|
| Brand assets | `docs/brand/` | `app-icon.png` only |
| System prompts | `docs/prompts/` | `HRMS-<TOPIC>.md` |
| Module notes | `docs/<module>/<TOPIC>.md` | Upper kebab, one topic |

Start at `docs/prompts/HRMS-SYSTEM.md`. Then open only the module file for the screen you are changing.

## System prompts

| File | Topic |
|---|---|
| `docs/prompts/HRMS-SYSTEM.md` | How to use these notes |
| `docs/prompts/HRMS-FREEZE.md` | Behaviour that must keep working |
| `docs/prompts/HRMS-BRAND.md` | App icon and colour tokens |
| `docs/prompts/HRMS-LIQUID-GLASS.md` | iPhone liquid-glass chrome and breakpoints |
| `docs/prompts/HRMS-PWA.md` | Installable app shell |
| `docs/prompts/HRMS-WORK-ORDER.md` | Order of work and done check |
| `docs/prompts/HRMS-LIVE-UI.md` | Ajax, jQuery, and Livewire without a full page load |
| `docs/prompts/HRMS-PAY-DAYS.md` | Per person clocks, Sunday, day or night, and gate-pass cutoff |
| `docs/prompts/HRMS-OUT-OVERTIME.md` | Gate pass column, OUT face window, and overtime after shift end |
| `docs/prompts/HRMS-LOCALE.md` | English, Hindi, and Gujarati for static words |
| `docs/prompts/HRMS-ERRORS.md` | Error pages for every HTTP code |

## Module notes

| Module folder | File | Topic |
|---|---|---|
| `auth` | `SIGN-IN.md` | Login, logout, authenticator |
| `auth` | `PASSWORD-RESET.md` | Forgot password email and new password |
| `roles` | `NAV-PERMISSIONS.md` | Which role sees which module |
| `employees` | `PROFILE.md` | Employee profile fields and the attendance card |
| `employees` | `PARENT-ROLE.md` | Role on the employee form, and who appears as Parent |
| `employees` | `AUTO-BLOCK.md` | Block people with no in or out for the days set in Settings |
| `attendance` | `REGISTER.md` | Past and current day, phone, worked hours |
| `attendance` | `GATE-PASS.md` | Gate pass request, approval, and office-work hours |
| `shifts` | `ROSTERS.md` | Shifts and rosters |
| `shifts` | `SCHEDULE.md` | Day and night by date for one person |
| `leave` | `REQUESTS.md` | Leave types and decisions |
| `leave` | `SCREEN.md` | Leave screen files and small-screen layout |
| `holidays` | `CALENDAR.md` | Holiday calendar |
| `payroll` | `RUNS.md` | Payroll run screen removed; salary sheets own the pay |
| `salary` | `STRUCTURES.md` | Salary Structures screen removed; amount is on Hours & pay |
| `salary` | `HOURS-AND-PAY.md` | Department hours, a person’s own hours, and monthly salary |
| `salary` | `SHEETS.md` | Generate a salary sheet for a department, people, and date range |
| `advances` | `DEDUCTIONS.md` | Advances and deductions |
| `devices` | `TERMINALS.md` | Device hub (Hikvision) |
| `reports` | `ANALYTICS.md` | Reports |
| `bank` | `TRANSFERS.md` | Bank accounts and transfer batches |
| `users` | `ACCOUNTS.md` | Logins are created from the employee |
| `roles` | `SEPARATION.md` | Permission plus AccessScope; what must not leak |
| `organization` | `DEPARTMENTS.md` | Departments |
| `organization` | `DESIGNATIONS.md` | Designations and employee types |
| `organization` | `STATUSES.md` | Employment statuses |
| `organization` | `FINANCIAL-YEARS.md` | Financial years |
| `organization` | `WORK-CALENDARS.md` | Work calendars |
| `organization` | `CUSTOM-FIELDS.md` | Custom fields |
| `organization` | `BRANDING.md` | Company branding screen |
| `organization` | `AUDIT-LOGS.md` | Read-only audit log |

Allowed module folders: `employees`, `attendance`, `shifts`, `leave`, `holidays`, `payroll`, `salary`, `advances`, `devices`, `reports`, `bank`, `users`, `roles`, `organization`, `auth`.

A new product module gets a new folder in that list, one `<TOPIC>.md` inside it, and a new row in this index. Cursor rule: `.cursor/rules/hrms-liquid-glass.mdc`.
