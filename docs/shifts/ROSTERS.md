# Shifts and rosters

Shift definitions, single-day roster assignment, and bulk roster assignment stay. Do not change how a shift feeds attendance calculation.

## Code

- `app/Http/Controllers/Operations/ShiftController.php`
- Routes: `shifts.index`, `shifts.store`, `shifts.destroy`, `shifts.roster`, `shifts.roster.bulk`

## Menu

After the permission split, this item uses `shifts.view`. Create, delete, and roster writes use `shifts.manage`. Grant both to roles that can open Shifts today, as listed in `docs/roles/NAV-PERMISSIONS.md`.

## Visual

Chrome and phone layout follow `docs/prompts/HRMS-LIQUID-GLASS.md`. Tables scroll inside `.tw` under 700px. Do not change roster save payloads.
