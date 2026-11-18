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
| `docs/prompts/HRMS-PAY-DAYS.md` | Paid holidays, Sunday 08:00–13:00, and salary cutoff |

## Module notes

| Module folder | File | Topic |
|---|---|---|
| `auth` | `SIGN-IN.md` | Login, logout, authenticator |
| `auth` | `PASSWORD-RESET.md` | Forgot password email and new password |
| `roles` | `NAV-PERMISSIONS.md` | Which role sees which module |
| `employees` | `PROFILE.md` | Profile fields still missing on the form |
| `employees` | `PARENT-ROLE.md` | Role on the employee form, and who appears as Parent |
| `attendance` | `REGISTER.md` | Past and current day, phone, worked hours |
| `shifts` | `ROSTERS.md` | Shifts and rosters |
| `leave` | `REQUESTS.md` | Leave types and decisions |
| `holidays` | `CALENDAR.md` | Holiday calendar |
| `payroll` | `RUNS.md` | Payroll runs |
| `salary` | `STRUCTURES.md` | Salary heads and structures |
| `salary` | `HOURS-AND-PAY.md` | Department hours, a person’s own hours, and monthly salary |
| `salary` | `SHEETS.md` | Generate a salary sheet for a department, people, and date range |
| `advances` | `DEDUCTIONS.md` | Advances and deductions |
| `devices` | `TERMINALS.md` | Device hub (Hikvision) |
| `reports` | `ANALYTICS.md` | Reports |
| `bank` | `TRANSFERS.md` | Bank accounts and transfer batches |
| `users` | `ACCOUNTS.md` | User accounts |
| `organization` | `DEPARTMENTS.md` | Departments |
| `organization` | `DESIGNATIONS.md` | Designations and employee types |
| `organization` | `STATUSES.md` | Employment statuses |
| `organization` | `FINANCIAL-YEARS.md` | Financial years |
| `organization` | `WORK-CALENDARS.md` | Work calendars |
| `organization` | `CUSTOM-FIELDS.md` | Custom fields |
| `organization` | `BRANDING.md` | Company branding screen |

Allowed module folders: `employees`, `attendance`, `shifts`, `leave`, `holidays`, `payroll`, `salary`, `advances`, `devices`, `reports`, `bank`, `users`, `roles`, `organization`, `auth`.

A new product module gets a new folder in that list, one `<TOPIC>.md` inside it, and a new row in this index. Cursor rule: `.cursor/rules/hrms-liquid-glass.mdc`.
