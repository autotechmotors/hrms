# Shifts and rosters

Shift definitions, single-day roster assignment, and bulk roster assignment stay. A roster night shift replaces the clock only on a Monday–Saturday date that has no saved day or night span. A saved span wins. See `docs/shifts/SCHEDULE.md`. Do not delete the shift or roster code.

## Code

- `app/Http/Controllers/Operations/ShiftController.php`
- Routes: `shifts.index`, `shifts.store`, `shifts.destroy`, `shifts.roster`, `shifts.roster.bulk`

## Menu

The Shifts & Rosters item is not in the menu. Day and night clocks are set on the employee and on Hours & pay. Do not delete the shift or roster code. Routes stay so an existing roster can still feed attendance.

## Visual

Chrome and phone layout follow `docs/prompts/HRMS-LIQUID-GLASS.md`. Tables scroll inside `.tw` under 700px. Do not change roster save payloads.
